<?php

declare(strict_types=1);

namespace Tests\Feature;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

final class ArchitectureBoundaryTest extends TestCase
{
    public function test_layer_roots_only_contain_responsibility_folders(): void
    {
        $root = dirname(__DIR__, 2);
        $layerRoots = glob($root.'/Modules/*/src/*', GLOB_ONLYDIR) ?: [];
        foreach (['Application', 'Domain', 'Infrastructure', 'Presentation'] as $layer) {
            if (is_dir($root.'/app/'.$layer)) {
                $layerRoots[] = $root.'/app/'.$layer;
            }
        }

        $violations = [];
        foreach ($layerRoots as $layerRoot) {
            foreach (glob($layerRoot.'/*.php') ?: [] as $file) {
                $violations[] = substr($file, strlen($root) + 1);
            }
        }

        self::assertSame([], $violations, 'Place PHP files inside responsibility folders, never directly in a layer root.');
    }

    public function test_all_use_case_handlers_can_be_resolved_with_the_registered_ports(): void
    {
        foreach ($this->phpFiles() as $file) {
            $path = str_replace('\\', '/', $file);
            if (! str_contains($path, '/Application/UseCases/') || ! str_ends_with($path, 'Handler.php')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            preg_match('/namespace\s+([^;]+);/', $source, $namespace);
            $class = $namespace[1].'\\'.basename($file, '.php');
            $this->assertInstanceOf($class, $this->app->make($class), $class);
            $entries = [];
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (! $method->isConstructor()) {
                    $entries[] = $method->getName();
                }
            }
            $this->assertSame(['handle'], $entries, $class.' exposes one use-case entry point.');
        }
    }

    public function test_domain_is_framework_and_adapter_independent(): void
    {
        foreach ($this->phpFiles() as $file) {
            $path = str_replace('\\', '/', $file);
            if (! preg_match('#/Modules/[^/]+/src/Domain/#', $path)) {
                continue;
            }
            $source = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/Illuminate\\\\|\\\\Infrastructure\\\\|\\\\Presentation\\\\|\b(?:DB|Schema)::|(?<!->)(?<!::)(?<!function )\b(?:app|resolve|config|now|request|response|collect)\(/', $source, $file);
        }
    }

    public function test_http_classes_and_routes_are_owned_by_presentation(): void
    {
        foreach ($this->phpFiles() as $file) {
            $path = str_replace('\\', '/', $file);
            $this->assertDoesNotMatchRegularExpression('#/src/Infrastructure/Http/|/Modules/[^/]+/routes/#', $path);
            if (str_contains($path, '/src/') && str_ends_with($path, 'Controller.php')) {
                $this->assertStringContainsString('/Presentation/Http/Controllers/', $path);
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function moduleComposerFiles(): iterable
    {
        foreach (glob(dirname(__DIR__, 2).'/Modules/*/composer.json') ?: [] as $file) {
            yield basename(dirname($file)) => [$file];
        }
    }

    #[DataProvider('moduleComposerFiles')]
    public function test_each_enabled_module_owns_package_compliant_psr4_autoload(string $file): void
    {
        $manifest = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $module = basename(dirname($file));
        $this->assertSame('src/', $manifest['autoload']['psr-4']["Modules\\{$module}\\"] ?? null, "{$module} must own its PSR-4 package mapping.");
    }

    public function test_domain_and_application_layers_do_not_define_eloquent_models(): void
    {
        $violations = [];
        foreach ($this->phpFiles() as $file) {
            if (! preg_match('#/src/(Domain|Application)/#', str_replace('\\', '/', $file))) {
                continue;
            }
            $contents = (string) file_get_contents($file);
            if (str_contains($contents, 'Illuminate\Database\Eloquent\Model') || preg_match('/extends\s+Model\b/', $contents)) {
                $violations[] = $file;
            }
        }
        $this->assertSame([], $violations, 'Eloquent models are infrastructure concerns.');
    }

    public function test_cross_module_imports_follow_the_approved_dependency_direction(): void
    {
        $allowed = [
            'ArchitectureProof' => ['ArchitectureProof'],
            'Foundation' => ['Foundation'],
            'Organization' => ['Organization', 'Foundation', 'Geography'],
            'Iam' => ['Iam', 'Foundation'],
            // Event readers resolve initiators through native Iam relations.
            'Audit' => ['Audit', 'Foundation', 'Iam'],
            'Outbox' => ['Outbox', 'Foundation'],
            'Authorization' => ['Authorization', 'Foundation', 'Iam', 'Organization'],
            'Notification' => ['Notification', 'Foundation', 'Iam'],
            'Consignment' => ['Consignment', 'Foundation', 'Geography', 'Pricing', 'Organization', 'Operations', 'Audit', 'Manifest', 'ServiceCatalog', 'Iam'],
            'Manifest' => ['Manifest', 'Foundation', 'Operations', 'Consignment', 'Organization', 'Audit'],
            'Dashboard' => ['Dashboard', 'Foundation', 'Audit', 'Consignment', 'Manifest', 'Operations', 'Organization'],
            // Catalog code allocation locks its owning tenant; schedule scopes read that tenant's nodes.
            'ServiceCatalog' => ['ServiceCatalog', 'Foundation', 'Organization', 'Audit', 'Geography', 'Pricing'],
            'Pricing' => ['Pricing', 'Foundation', 'Geography', 'ServiceCatalog', 'Organization', 'Audit'],
            'Geography' => ['Geography', 'Foundation'],
            // Drafts validate their assignee through Iam and their industry through the CrmCatalog contract.
            // The history page also reads the sales documents and the change trail of the file. Finance is
            // NOT listed on purpose: CrmFinance reads Customer, so it fills a Customer port instead.
            'Customer' => ['Customer', 'Foundation', 'Geography', 'Iam', 'CrmCatalog', 'CrmTask', 'CrmOpportunitie', 'CrmSales', 'Audit'],
            // The shared industry knowledge base is read through the Foundation access contracts only.
            'CrmCatalog' => ['CrmCatalog', 'Foundation'],
            // A task names the user it is assigned to, as every record that carries an owner does.
            'CrmTask' => ['CrmTask', 'Foundation', 'Iam'],
            // The link registry resolves each kind of record against the module that owns it, so the
            // archive can refuse a document attached to something nobody can look up.
            'DocumentStore' => ['DocumentStore', 'Foundation', 'Customer', 'CrmOpportunitie'],
            // The financial tab of the customer file: it proves the customer and the opportunity it
            // references through the repository contracts of the modules that own them.
            'CrmFinance' => ['CrmFinance', 'Foundation', 'Customer', 'CrmOpportunitie'],
            // The board card names its customer, its owner and its next task, and winning a deal is
            // judged against the customer phase and the interaction that proves the acceptance.
            'CrmOpportunitie' => ['CrmOpportunitie', 'Foundation', 'Customer', 'CrmTask', 'Iam'],
            // A sales document is drawn up for a customer against an opportunity, and names both.
            'CrmSales' => ['CrmSales', 'Foundation', 'Customer', 'CrmOpportunitie', 'Iam'],
            // A team never owns a task; it only fills the CrmTask port that names the team a
            // referral was recorded against.
            // A team names its supervisor and its members as users, as every record with an owner does.
            'CrmTeam' => ['CrmTeam', 'Foundation', 'CrmTask', 'Iam'],
            // Coverage rules reference Organization nodes through an explicit Eloquent relation.
            'Operations' => ['Operations', 'Foundation', 'Geography', 'Consignment', 'ServiceCatalog', 'Organization', 'Iam'],
        ];
        $violations = [];
        foreach ($this->phpFiles() as $file) {
            $normalized = str_replace('\\', '/', $file);
            if (! preg_match('#/Modules/([^/]+)/#', $normalized, $ownerMatch)) {
                continue;
            }
            preg_match_all('/(?:^use\s+|\\\\)Modules\\\\([^\\\\\\s]+)\\\\/m', (string) file_get_contents($file), $imports);
            foreach ($imports[1] as $importedModule) {
                if (! in_array($importedModule, $allowed[$ownerMatch[1]] ?? [$ownerMatch[1]], true)) {
                    $violations[] = "{$ownerMatch[1]} imports {$importedModule} in {$file}";
                }
            }
        }
        $this->assertSame([], $violations);
    }

    public function test_dtos_live_in_a_dto_folder_and_carry_the_dto_suffix(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertSame([], glob($root.'/Modules/*/src/*/Data') ?: [],
            'Plain-data classes belong in a Dto folder, not Data.');

        $outsideApplication = array_values(array_filter(
            glob($root.'/Modules/*/src/*/Dto') ?: [],
            static fn (string $dir): bool => ! str_ends_with($dir, '/Application/Dto'),
        ));
        self::assertSame([], $outsideApplication,
            'Dto folders belong to the Application layer; Domain carries ValueObjects instead.');

        $misnamed = [];
        foreach (glob($root.'/Modules/*/src/*/Dto/*.php') ?: [] as $file) {
            if (! str_ends_with(basename($file, '.php'), 'Dto')) {
                $misnamed[] = substr($file, strlen($root) + 1);
            }
        }
        self::assertSame([], $misnamed, 'Every class in a Dto folder must end with Dto.');

        $misplaced = [];
        foreach ($this->phpFiles() as $file) {
            $path = str_replace('\\', '/', $file);
            if (str_ends_with(basename($path, '.php'), 'Dto') && ! str_contains($path, '/Dto/')) {
                $misplaced[] = substr($file, strlen($root) + 1);
            }
        }
        self::assertSame([], $misplaced, 'Every *Dto class must live in a Dto folder.');
    }

    public function test_services_live_only_in_the_application_layer(): void
    {
        $strays = array_merge(
            glob(dirname(__DIR__, 2).'/Modules/*/src/Domain/Services') ?: [],
            glob(dirname(__DIR__, 2).'/Modules/*/src/Infrastructure/Services') ?: [],
        );

        self::assertSame([], $strays, 'Services belong in Application/Services; framework adapters belong in Infrastructure/Adapters.');
    }

    public function test_application_services_and_their_boundary_contracts_are_resolvable(): void
    {
        foreach (glob(dirname(__DIR__, 2).'/Modules/*/src/Application/Services/*.php') as $file) {
            preg_match('/namespace\s+([^;]+);/', file_get_contents($file), $namespace);
            $class = $namespace[1].'\\'.basename($file, '.php');
            $reflection = new ReflectionClass($class);
            $instanceMethods = array_filter($reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                fn (ReflectionMethod $method): bool => ! $method->isConstructor() && ! $method->isStatic());
            if ($instanceMethods === []) {
                continue;
            }
            $contracts = array_filter($reflection->getInterfaceNames(),
                fn (string $name): bool => str_contains($name, '\\Contracts\\'));
            $serviceContract = str_replace('\\Services\\', '\\Contracts\\', $class).'Interface';
            self::assertTrue(interface_exists($serviceContract), $class.' must declare its service contract.');
            self::assertTrue($reflection->implementsInterface($serviceContract), $class.' must implement its service contract.');
            self::assertInstanceOf($class, $this->app->make($class), $class);
            foreach ($contracts as $contract) {
                self::assertInstanceOf($contract, $this->app->make($contract), $class);
            }
        }
    }

    public function test_application_services_are_injected_through_contracts(): void
    {
        $violations = [];
        foreach ($this->phpFiles() as $file) {
            if (! str_contains(str_replace('\\', '/', $file), '/src/')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            if (! preg_match('/namespace\s+([^;]+);/', $source, $namespace)
                || ! preg_match('/\bclass\s+(\w+)/', $source, $declaration)) {
                continue;
            }
            $class = $namespace[1].'\\'.$declaration[1];
            if (! class_exists($class)) {
                continue;
            }
            $constructor = (new ReflectionClass($class))->getConstructor();
            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && str_contains($type->getName(), '\\Services\\')) {
                    $violations[] = $class.'::$'.$parameter->getName();
                }
            }
        }
        self::assertSame([], $violations, 'Inject the service contract; instantiate concrete services only in composition roots and tests.');
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/Modules', FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
