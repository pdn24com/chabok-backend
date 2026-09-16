<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
return new class extends Migration {
 public function up(): void {
  $code='operational_status.manage';
  $id=DB::table('permissions')->where('permission_code',$code)->value('permission_id') ?? (string)Str::uuid();
  DB::table('permissions')->updateOrInsert(['permission_code'=>$code],['permission_id'=>$id,'module_code'=>'Consignment','resource_code'=>'operational_status','action_code'=>'manage','description'=>'Manage tenant operational status catalogue.','status'=>'ACTIVE','created_at'=>now(),'updated_at'=>now()]);
  foreach(DB::table('roles')->where(['owner_key'=>'GLOBAL','role_code'=>'hq_admin'])->pluck('role_id') as $role) DB::table('role_permissions')->insertOrIgnore(['role_permission_id'=>(string)Str::uuid(),'role_id'=>$role,'permission_id'=>$id,'created_by'=>null,'created_at'=>now()]);
 }
 public function down(): void { /* Keep assignments and audit-compatible permission identity. */ }
};
