<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArchitectureBoundaryTest extends TestCase
{
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

        $this->assertSame(
            'src/',
            $manifest['autoload']['psr-4']["Modules\\{$module}\\"] ?? null,
            "{$module} must own its PSR-4 package mapping.",
        );
    }

    public function test_domain_and_application_layers_do_not_define_eloquent_models(): void
    {
        $violations = [];
        foreach ($this->phpFiles() as $file) {
            if (! preg_match('#/src/(Domain|Application)/#', str_replace('\\', '/', $file))) {
                continue;
            }
            $contents = (string) file_get_contents($file);
            if (
                str_contains($contents, 'Illuminate\\Database\\Eloquent\\Model')
                || preg_match('/extends\s+Model\b/', $contents)
            ) {
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
            'Organization' => ['Organization', 'Foundation'],
            'User' => ['User', 'Foundation'],
            'Identity' => ['Identity', 'Foundation', 'User'],
            'Audit' => ['Audit', 'Foundation'],
            'Outbox' => ['Outbox', 'Foundation'],
            'Authorization' => ['Authorization', 'Foundation', 'Identity', 'User'],
            'Notification' => ['Notification', 'Foundation'],
        ];
        $violations = [];

        foreach ($this->phpFiles() as $file) {
            $normalized = str_replace('\\', '/', $file);
            if (! preg_match('#/Modules/([^/]+)/#', $normalized, $ownerMatch)) {
                continue;
            }
            preg_match_all('/^use\s+Modules\\\\([^\\\\]+)\\\\/m', (string) file_get_contents($file), $imports);
            foreach ($imports[1] as $importedModule) {
                if (! in_array($importedModule, $allowed[$ownerMatch[1]] ?? [$ownerMatch[1]], true)) {
                    $violations[] = "{$ownerMatch[1]} imports {$importedModule} in {$file}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                dirname(__DIR__, 2).'/Modules',
                \FilesystemIterator::SKIP_DOTS,
            ),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
