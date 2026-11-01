<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\MaterialManagement\MaterialCatalogService;
use ProjectO\Modules\MaterialManagement\MaterialManagementPlugin;
use ProjectO\Modules\MaterialManagement\ProjectMaterialService;
use ProjectO\Modules\ProjectFoundation\ProjectSpaceService;
use ProjectO\Plugin\PluginContext;
use ProjectO\Plugin\PluginRegistry;
use ProjectO\Plugin\PluginResponse;
use ProjectO\Storage\ObjectStorageInterface;
use ProjectO\Storage\StorageFactory;
use ProjectO\Support;

require dirname(__DIR__) . '/vendor/autoload.php';

function materialTestCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function materialTestExpectStatus(callable $operation, int $status, string $message): void
{
    try {
        $operation();
        throw new RuntimeException($message);
    } catch (HttpException $exception) {
        if ($exception->statusCode !== $status) {
            throw $exception;
        }
    }
}

function renderMaterialTestView(PluginResponse $response): string
{
    if ($response->type !== 'view' || $response->viewPath === null) {
        throw new RuntimeException('Expected a material-management view response.');
    }

    $escape = [Support::class, 'escape'];
    $csrfToken = 'material-test-token';
    $username = 'admin';
    $canManagePlatform = false;
    $theme = 'light';
    $flash = null;
    $viewData = $response->data;
    extract($viewData, EXTR_SKIP);
    ob_start();
    require $response->viewPath;
    return (string) ob_get_clean();
}

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec(
    'CREATE TABLE Projects (
        project_id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_name TEXT NOT NULL,
        start_date TEXT,
        end_date TEXT,
        status TEXT
    )'
);
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, is_active INTEGER NOT NULL DEFAULT 1)');
$db->exec('CREATE TABLE Roles (role_id INTEGER PRIMARY KEY, role_name TEXT)');
$db->exec('CREATE TABLE User_Roles (user_id INTEGER, role_id INTEGER)');
$db->exec('CREATE TABLE Permissions (permission_id INTEGER PRIMARY KEY AUTOINCREMENT, permission_name TEXT UNIQUE, display_name TEXT, description TEXT, module TEXT)');
$db->exec('CREATE TABLE Role_Permissions (role_id INTEGER, permission_id INTEGER)');
$db->exec('CREATE TABLE Plugin_States (plugin_id TEXT PRIMARY KEY, is_enabled INTEGER)');
$db->exec(
    'CREATE TABLE System_Settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_by INTEGER,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$db->exec(
    "INSERT INTO System_Settings (setting_key, setting_value) VALUES
        ('theme', 'light'),
        ('language', 'zh-CN'),
        ('default_country_code', 'CN'),
        ('default_currency_code', 'CNY'),
        ('admin_guard', '1')"
);
$db->exec(
    'CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, details TEXT,
        ip_address TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$db->exec("INSERT INTO Projects (project_name, status) VALUES ('Project One', 'planning'), ('Project Two', 'planning')");
$db->exec("INSERT INTO Users (user_id, username, is_active) VALUES (1, 'admin', 1)");
$db->exec("INSERT INTO Roles (role_id, role_name) VALUES (1, 'administrator')");
$db->exec('INSERT INTO User_Roles (user_id, role_id) VALUES (1, 1)');

$registry = new PluginRegistry($db, dirname(__DIR__) . '/modules');
$permissionInsert = $db->prepare(
    'INSERT INTO Permissions (permission_name, display_name, description, module)
     VALUES (:name, :display_name, :description, :module)'
);
$rolePermissionInsert = $db->prepare(
    'INSERT INTO Role_Permissions (role_id, permission_id) VALUES (1, :permission_id)'
);
foreach ($registry->permissions() as $permission) {
    $permissionInsert->execute([
        'name' => $permission['name'],
        'display_name' => $permission['display_name'],
        'description' => $permission['description'] ?? null,
        'module' => $permission['module'],
    ]);
    $rolePermissionInsert->execute(['permission_id' => (int) $db->lastInsertId()]);
}

$db->exec(
    'CREATE TABLE Material_Categories (
        category_id INTEGER PRIMARY KEY AUTOINCREMENT, category_name TEXT NOT NULL,
        parent_category_id INTEGER, sort_order INTEGER NOT NULL DEFAULT 0
    )'
);
$db->exec(
    'CREATE TABLE Materials (
        material_id INTEGER PRIMARY KEY AUTOINCREMENT, material_code TEXT UNIQUE,
        material_name TEXT NOT NULL, unit TEXT NOT NULL, specifications TEXT,
        material_description_detailed TEXT, category_id INTEGER, default_quotation_id INTEGER
    )'
);
$db->exec('CREATE TABLE Brands (brand_id INTEGER PRIMARY KEY AUTOINCREMENT, brand_name TEXT UNIQUE, external_link TEXT)');
$db->exec(
    'CREATE TABLE Suppliers (
        supplier_id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_name TEXT NOT NULL,
        contact_person TEXT, phone_number TEXT, address TEXT, country_code TEXT, website_url TEXT
    )'
);
$db->exec(
    'CREATE TABLE Supplier_Materials (
        supplier_material_id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_id INTEGER NOT NULL,
        brand_id INTEGER, supplier_material_code TEXT NOT NULL, supplier_material_name TEXT NOT NULL,
        lead_time_days INTEGER, material_details TEXT, specification TEXT,
        coverage_quantity NUMERIC, coverage_unit TEXT, price_total NUMERIC, currency_code TEXT,
        UNIQUE (supplier_id, supplier_material_code)
    )'
);
$db->exec(
    'CREATE TABLE Material_Quotations (
        quotation_id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_material_id INTEGER NOT NULL,
        material_id INTEGER NOT NULL, purchase_unit_price NUMERIC NOT NULL, quotation_date TEXT NOT NULL,
        currency_code TEXT NOT NULL, min_order_quantity NUMERIC, confirmed_tax_rate NUMERIC NOT NULL DEFAULT 0,
        version INTEGER NOT NULL DEFAULT 1
    )'
);
$db->exec(
    'CREATE TABLE Material_Mappings (
        mapping_id INTEGER PRIMARY KEY AUTOINCREMENT, material_id INTEGER NOT NULL,
        quotation_id INTEGER NOT NULL UNIQUE, supplier_material_id INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE Auxiliary_Materials (
        aux_id INTEGER PRIMARY KEY AUTOINCREMENT, aux_name TEXT NOT NULL, unit TEXT NOT NULL, description TEXT
    )'
);
$db->exec(
    'CREATE TABLE Buildings (
        building_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL,
        building_number TEXT NOT NULL, building_name TEXT NOT NULL,
        UNIQUE (project_id, building_number)
    )'
);
$db->exec(
    'CREATE TABLE Building_Floors (
        floor_id INTEGER PRIMARY KEY AUTOINCREMENT, building_id INTEGER NOT NULL,
        floor_number TEXT NOT NULL, floor_name TEXT, usage_type TEXT,
        UNIQUE (floor_id, building_id)
    )'
);
$db->exec(
    'CREATE TABLE Construction_Areas (
        area_id INTEGER PRIMARY KEY AUTOINCREMENT, building_id INTEGER NOT NULL,
        area_name TEXT NOT NULL, description TEXT
    )'
);
$db->exec(
    'CREATE TABLE Rooms (
        room_id INTEGER PRIMARY KEY AUTOINCREMENT, building_id INTEGER NOT NULL, floor_id INTEGER NOT NULL,
        room_number TEXT NOT NULL, room_type TEXT, room_area NUMERIC, room_height NUMERIC,
        room_status TEXT DEFAULT "空闲", UNIQUE (room_id, building_id, floor_id)
    )'
);
$db->exec(
    'CREATE TABLE Construction_Area_Rooms (
        area_room_id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL, area_id INTEGER NOT NULL,
        floor_id INTEGER NOT NULL, building_id INTEGER NOT NULL, room_number TEXT NOT NULL, remarks TEXT
    )'
);
$db->exec(
    'CREATE TABLE Initial_Cost_Estimate_Detail (
        estimate_detail_id INTEGER PRIMARY KEY AUTOINCREMENT, building_id INTEGER NOT NULL,
        floor_id INTEGER, room_id INTEGER, material_id INTEGER NOT NULL,
        estimated_quantity NUMERIC NOT NULL DEFAULT 0, remarks TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$db->exec(
    'CREATE TABLE Material_Samples (
        sample_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL, quotation_id INTEGER NOT NULL,
        sample_quantity INTEGER NOT NULL DEFAULT 0, color TEXT, document_status TEXT NOT NULL,
        sent_status TEXT NOT NULL, sent_date TEXT, owner_approval_status TEXT NOT NULL,
        owner_approval_date TEXT, remarks TEXT, created_by INTEGER NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
        archived_at TEXT, archived_by INTEGER
    )'
);
$db->exec(
    'CREATE TABLE Sample_Location_Map (
        sample_id INTEGER PRIMARY KEY, project_id INTEGER NOT NULL, location_type TEXT NOT NULL,
        location_id INTEGER NOT NULL, remarks TEXT
    )'
);
$db->exec(
    'CREATE TABLE Material_Sample_Images (
        image_id INTEGER PRIMARY KEY AUTOINCREMENT, sample_id INTEGER NOT NULL, project_id INTEGER NOT NULL,
        object_key TEXT NOT NULL, original_name TEXT NOT NULL, content_type TEXT NOT NULL,
        file_size INTEGER NOT NULL, uploaded_by INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);

$catalog = new MaterialCatalogService($db);
$rootCategoryId = $catalog->saveCategory(['category_name' => '装修材料', 'sort_order' => '10']);
$childCategoryId = $catalog->saveCategory([
    'category_name' => '地面材料',
    'parent_category_id' => (string) $rootCategoryId,
]);
$materialId = $catalog->saveMaterial([
    'material_code' => 'FL-001',
    'material_name' => '地砖',
    'unit' => 'm2',
    'category_id' => (string) $childCategoryId,
    'specifications' => '600×600',
]);
$brandId = $catalog->saveBrand(['brand_name' => '示例品牌', 'external_link' => 'https://example.test/brand']);
$supplierId = $catalog->saveSupplier([
    'supplier_name' => '供应商甲',
    'country_code' => 'cn',
    'website_url' => 'https://example.test',
]);
$supplierMaterialId = $catalog->saveSupplierMaterial([
    'supplier_id' => (string) $supplierId,
    'brand_id' => (string) $brandId,
    'supplier_material_code' => 'A-001',
    'supplier_material_name' => '600 方砖',
    'lead_time_days' => '12',
    'currency_code' => 'cny',
]);
$quoteId = $catalog->saveQuotation([
    'material_id' => (string) $materialId,
    'supplier_material_id' => (string) $supplierMaterialId,
    'purchase_unit_price' => '123.4500',
    'quotation_date' => '2026-03-01',
    'currency_code' => 'cny',
    'min_order_quantity' => '10.0000',
    'confirmed_tax_rate' => '13.00',
]);
materialTestCheck((int) $db->query('SELECT version FROM Material_Quotations')->fetchColumn() === 1, 'New quotations must start at version 1.');
materialTestCheck((string) $db->query('SELECT confirmed_tax_rate FROM Material_Quotations')->fetchColumn() === '0.13', 'Tax percentage must be stored as its confirmed decimal snapshot.');
materialTestCheck((int) $db->query('SELECT COUNT(*) FROM Material_Mappings')->fetchColumn() === 1, 'Saving a quotation must maintain the material mapping.');
$catalog->setDefaultQuotation($materialId, $quoteId);
materialTestCheck((int) $db->query('SELECT default_quotation_id FROM Materials')->fetchColumn() === $quoteId, 'Default quotation selection must persist explicitly.');

$catalog->saveQuotation([
    'quotation_id' => (string) $quoteId,
    'version' => '1',
    'material_id' => (string) $materialId,
    'supplier_material_id' => (string) $supplierMaterialId,
    'purchase_unit_price' => '124.0000',
    'quotation_date' => '2026-03-02',
    'currency_code' => 'CNY',
    'min_order_quantity' => '10',
    'confirmed_tax_rate' => '13',
]);
materialTestCheck((int) $db->query('SELECT version FROM Material_Quotations')->fetchColumn() === 2, 'Quotation edits must increment the version.');
materialTestExpectStatus(
    static fn () => $catalog->saveQuotation([
        'quotation_id' => (string) $quoteId,
        'version' => '1',
        'material_id' => (string) $materialId,
        'supplier_material_id' => (string) $supplierMaterialId,
        'purchase_unit_price' => '125',
        'quotation_date' => '2026-03-03',
        'currency_code' => 'CNY',
    ]),
    409,
    'Stale quotation versions must be rejected.'
);
materialTestExpectStatus(
    static fn () => $catalog->saveCategory([
        'category_id' => (string) $rootCategoryId,
        'category_name' => '装修材料',
        'parent_category_id' => (string) $childCategoryId,
    ]),
    422,
    'Category cycles must be rejected.'
);
materialTestExpectStatus(
    static fn () => $catalog->deleteCategory($childCategoryId),
    409,
    'Categories used by materials must not be deleted.'
);
materialTestExpectStatus(
    static fn () => $catalog->deleteBrand($brandId),
    409,
    'Brands used by supplier materials must not be deleted.'
);
materialTestExpectStatus(
    static fn () => $catalog->deleteSupplierMaterial($supplierMaterialId),
    409,
    'Supplier materials used by quotations must not be deleted.'
);
materialTestExpectStatus(
    static fn () => $catalog->saveBrand(['brand_name' => '错误链接', 'external_link' => 'javascript:alert(1)']),
    422,
    'Non-HTTP external links must be rejected.'
);
$auxId = $catalog->saveAuxiliary(['aux_name' => '填缝剂', 'unit' => 'kg']);
materialTestCheck((int) $db->query('SELECT COUNT(*) FROM Auxiliary_Materials WHERE aux_id = ' . $auxId)->fetchColumn() === 1, 'Auxiliary materials must be manageable.');

$spaceService = new ProjectSpaceService($db);
$buildingId = $spaceService->save(1, [
    'type' => 'building',
    'number' => 'A',
    'name' => 'A 栋',
]);
$floorId = $spaceService->save(1, [
    'type' => 'floor',
    'building_id' => (string) $buildingId,
    'number' => '1F',
    'name' => '首层',
]);
$areaId = $spaceService->save(1, [
    'type' => 'area',
    'building_id' => (string) $buildingId,
    'name' => '公共走廊',
]);
$roomId = $spaceService->save(1, [
    'type' => 'room',
    'building_id' => (string) $buildingId,
    'floor_id' => (string) $floorId,
    'number' => '101',
]);
$spaceService->mapRoom(1, [
    'area_id' => (string) $areaId,
    'floor_id' => (string) $floorId,
    'room_id' => (string) $roomId,
    'remarks' => '北侧',
]);
materialTestCheck(count($spaceService->list(1)[0]['area_rooms']) === 1, 'Project-space area/room mappings must be listed.');
materialTestExpectStatus(
    static fn () => $spaceService->save(2, [
        'type' => 'room',
        'building_id' => (string) $buildingId,
        'floor_id' => (string) $floorId,
        'number' => 'cross-project',
    ]),
    404,
    'Space records must not be created under another project.'
);
materialTestExpectStatus(
    static fn () => $spaceService->save(1, [
        'type' => 'building',
        'number' => 'A',
        'name' => '重复编号楼栋',
    ]),
    422,
    'Duplicate building numbers in a project must produce a validation error.'
);
$spaceService->save(1, [
    'type' => 'building',
    'id' => (string) $buildingId,
    'number' => 'A',
    'name' => 'A栋已更新',
]);
materialTestCheck(
    (string) $db->query('SELECT building_name FROM Buildings WHERE building_id = ' . $buildingId)->fetchColumn() === 'A栋已更新',
    'A building must be allowed to retain its own number when updated.'
);

$projectMaterials = new ProjectMaterialService($db);
$estimateId = $projectMaterials->saveEstimate(1, [
    'building_id' => (string) $buildingId,
    'floor_id' => (string) $floorId,
    'room_id' => (string) $roomId,
    'material_id' => (string) $materialId,
    'estimated_quantity' => '12.3456',
    'remarks' => '首层试算',
]);
materialTestCheck((string) $projectMaterials->estimateData(1)[0]['estimated_quantity'] === '12.3456', 'Estimate quantities must preserve decimal precision without cost calculations.');
$secondBuildingId = $spaceService->save(2, ['type' => 'building', 'number' => 'B', 'name' => 'B 栋']);
materialTestExpectStatus(
    static fn () => $projectMaterials->saveEstimate(1, [
        'building_id' => (string) $secondBuildingId,
        'material_id' => (string) $materialId,
        'estimated_quantity' => '1',
    ]),
    404,
    'Estimates must reject a building owned by another project.'
);
materialTestExpectStatus(
    static fn () => $projectMaterials->deleteEstimate(2, $estimateId),
    404,
    'Estimate deletion must be scoped to its owning project.'
);

$sampleId = $projectMaterials->saveSample(1, 1, [
    'quotation_id' => (string) $quoteId,
    'sample_quantity' => '2',
    'color' => '暖灰',
    'document_status' => 'submitted',
    'sent_status' => 'partially_sent',
    'sent_date' => '2026-03-03',
    'owner_approval_status' => 'approved',
    'owner_approval_date' => '2026-03-04',
    'location_type' => 'floor',
    'location_id' => (string) $floorId,
    'remarks' => '一层样板',
]);
materialTestExpectStatus(
    static fn () => $projectMaterials->saveSample(2, 1, [
        'quotation_id' => (string) $quoteId,
        'location_type' => 'floor',
        'location_id' => (string) $floorId,
    ]),
    404,
    'Sample locations must not cross project boundaries.'
);
$db->exec("UPDATE Material_Samples SET owner_approval_status = '已审批通过' WHERE sample_id = {$sampleId}");
$samples = $projectMaterials->listSamples(1);
materialTestCheck($samples[0]['location_name'] === 'A 1F 首层', 'Samples must show the selected project floor.');
materialTestCheck($samples[0]['owner_approval_status'] === 'approved', 'The legacy approved label must be normalized for display.');
$projectMaterials->addImage(1, $sampleId, 1, 'samples/1/' . $sampleId . '/test.png', 'test.png', 'image/png', 123);
materialTestExpectStatus(
    static fn () => $catalog->deleteQuotation($quoteId),
    409,
    'Quotations referenced by samples must not be deleted.'
);
$projectMaterials->archiveSample(1, $sampleId, 1);
materialTestCheck($projectMaterials->listSamples(1) === [], 'Archived samples must be excluded from active tracking.');
materialTestCheck((int) $db->query('SELECT COUNT(*) FROM Material_Sample_Images')->fetchColumn() === 1, 'Archiving a sample must retain its images.');

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$_SERVER = [];
$plugin = new MaterialManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/material-management')
);
$materialPage = $plugin->dispatch('materials', []);
materialTestCheck($materialPage->type === 'view' && is_file((string) $materialPage->viewPath), 'Global material catalog should not require an active project.');
$samplePage = $plugin->dispatch('samples', []);
materialTestCheck($samplePage->type === 'view' && is_file((string) $samplePage->viewPath), 'Sample tracking should resolve its plugin view.');
$estimatePage = $plugin->dispatch('estimates', []);
materialTestCheck($estimatePage->type === 'view' && is_file((string) $estimatePage->viewPath), 'Material estimates should resolve their plugin view.');
foreach ([
    [$materialPage, '材料目录'],
    [$plugin->dispatch('quotations', []), '材料报价'],
    [$samplePage, '样品跟踪'],
    [$estimatePage, '材料估算明细'],
] as [$pageResponse, $expectedHeading]) {
    materialTestCheck(
        str_contains(renderMaterialTestView($pageResponse), $expectedHeading),
        "Material-management page should render its expected heading: {$expectedHeading}."
    );
}

$_SERVER['REQUEST_METHOD'] = 'GET';
foreach ([
    'materials' => '材料与分类',
    'quotations' => '材料报价',
] as $page => $heading) {
    $_GET = ['page' => $page];
    http_response_code(200);
    ob_start();
    (new \ProjectO\Application($db))->run();
    $html = (string) ob_get_clean();
    materialTestCheck(
        http_response_code() === 200 && str_contains($html, '<h1>' . $heading . '</h1>'),
        "Application must render the {$page} page data without errors."
    );
}
$db->exec(
    "DELETE FROM Role_Permissions
     WHERE permission_id = (SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_quotations')"
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('samples', []),
    403,
    'Sample tracking must not expose quotation data without quotation-view permission.'
);
$quotationPermissionId = (int) $db->query(
    "SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_quotations'"
)->fetchColumn();
$rolePermissionInsert->execute(['permission_id' => $quotationPermissionId]);

$migrationKeys = array_keys($registry->migrations());
$spaceMigrationPosition = array_search('project.foundation/202611010004_project_spaces.sql', $migrationKeys, true);
$materialMigrationPosition = array_search('material.management/202611010005_material_management.sql', $migrationKeys, true);
materialTestCheck(
    $spaceMigrationPosition !== false && $materialMigrationPosition !== false
        && $spaceMigrationPosition < $materialMigrationPosition,
    'Plugin migrations must follow declared dependencies.'
);

$previousStorageAdapter = getenv('APP_OBJECT_STORAGE_ADAPTER');
putenv('APP_OBJECT_STORAGE_ADAPTER');
materialTestCheck(StorageFactory::fromEnvironment() === null, 'Object storage must remain optional when no adapter is configured.');
putenv('APP_OBJECT_STORAGE_ADAPTER=ProjectO\\Modules\\MaterialManagement\\R2SampleImageStorage');
$storageAdapter = StorageFactory::fromEnvironment();
materialTestCheck(
    $storageAdapter instanceof ObjectStorageInterface,
    'Configured storage adapters must implement the core object-storage interface.'
);
if ($previousStorageAdapter === false) {
    putenv('APP_OBJECT_STORAGE_ADAPTER');
} else {
    putenv('APP_OBJECT_STORAGE_ADAPTER=' . $previousStorageAdapter);
}

unset($_SESSION['active_project_id']);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('samples', []),
    409,
    'Sample pages must require a current project.'
);
$_SESSION['active_project_id'] = 1;
$db->exec(
    "DELETE FROM Role_Permissions
     WHERE permission_id = (SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_samples')"
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('samples', []),
    403,
    'Sample pages must enforce server-side feature permissions.'
);

echo "Material management tests passed.\n";
