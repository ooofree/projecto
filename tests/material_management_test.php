<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\MaterialManagement\MaterialCatalogService;
use ProjectO\Modules\MaterialManagement\MaterialManagementPlugin;
use ProjectO\Modules\MaterialManagement\ProjectMaterialService;
use ProjectO\Export\XlsxWorkbook;
use ProjectO\Storage\CloudStorageSettings;
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

function materialTestXlsxPart(PluginResponse $response, string $part): string
{
    $body = $response->data['body'] ?? null;
    if ($response->type !== 'download' || !is_string($body)) {
        throw new RuntimeException('Expected an xlsx download response.');
    }
    $path = tempnam(sys_get_temp_dir(), 'material-xlsx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary file for the xlsx export.');
    }
    file_put_contents($path, $body);
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        @unlink($path);
        throw new RuntimeException('Export is not a readable xlsx zip package.');
    }
    $xml = $zip->getFromName($part);
    $zip->close();
    @unlink($path);
    if (!is_string($xml) || $xml === '') {
        throw new RuntimeException('Export is missing its ' . $part . ' part.');
    }

    return $xml;
}

function materialTestXlsxSheet(PluginResponse $response): string
{
    return materialTestXlsxPart($response, 'xl/worksheets/sheet1.xml');
}

final class MaterialTestImageStorage implements ObjectStorageInterface
{
    public static string $fileUrl = '';

    public function __construct(?PDO $db = null)
    {
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function upload(string $objectKey, string $filePath, string $contentType): void
    {
    }

    public function signedReadUrl(string $objectKey, string $contentType): string
    {
        return self::$fileUrl;
    }

    public function read(string $objectKey): string
    {
        $contents = @file_get_contents(self::$fileUrl);

        return is_string($contents) ? $contents : '';
    }

    public function delete(string $objectKey): void
    {
    }
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
$db->exec(
    'CREATE TABLE project_foundation_archive_records (
        archive_id INTEGER PRIMARY KEY AUTOINCREMENT,
        entity_type TEXT NOT NULL,
        entity_id INTEGER NOT NULL,
        project_id INTEGER NOT NULL REFERENCES Projects (project_id),
        archived_by INTEGER NOT NULL,
        archived_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (entity_type, entity_id)
    )'
);
$db->exec('CREATE TABLE Users (user_id INTEGER PRIMARY KEY, username TEXT, is_active INTEGER NOT NULL DEFAULT 1, timezone TEXT)');
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
        ('timezone', 'Asia/Shanghai'),
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
        category_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, category_name TEXT NOT NULL,
        parent_category_id INTEGER, sort_order INTEGER NOT NULL DEFAULT 0
    )'
);
$db->exec(
    'CREATE TABLE Materials (
        material_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER,
        material_code TEXT, material_name TEXT NOT NULL, unit TEXT NOT NULL, specifications TEXT,
        material_description_detailed TEXT, category_id INTEGER, default_quotation_id INTEGER,
        UNIQUE (project_id, material_code)
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
        quotation_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, supplier_material_id INTEGER NOT NULL,
        material_id INTEGER NOT NULL, purchase_unit_price NUMERIC NOT NULL, quotation_date TEXT NOT NULL,
        currency_code TEXT NOT NULL, min_order_quantity NUMERIC, confirmed_tax_rate NUMERIC NOT NULL DEFAULT 0,
        version INTEGER NOT NULL DEFAULT 1
    )'
);
$db->exec(
    'CREATE TABLE Material_Mappings (
        mapping_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, material_id INTEGER NOT NULL,
        quotation_id INTEGER NOT NULL UNIQUE, supplier_material_id INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE Tender_List (
        tender_item_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL,
        material_id INTEGER NOT NULL, tender_quantity NUMERIC NOT NULL,
        tender_unit_price NUMERIC NOT NULL, currency_code TEXT NOT NULL DEFAULT "CNY",
        status TEXT NOT NULL DEFAULT "pending", updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$db->exec(
    'CREATE TABLE Auxiliary_Materials (
        aux_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER,
        aux_name TEXT NOT NULL, unit TEXT NOT NULL, description TEXT
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
        sample_id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL,
        material_id INTEGER NOT NULL, supplier_id INTEGER NOT NULL, quotation_id INTEGER,
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
$rootCategoryId = $catalog->saveCategory(1, ['category_name' => '装修材料', 'sort_order' => '10']);
$childCategoryId = $catalog->saveCategory(1, [
    'category_name' => '地面材料',
    'parent_category_id' => (string) $rootCategoryId,
]);
$materialId = $catalog->saveMaterial(1, [
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
materialTestExpectStatus(
    static fn () => $catalog->saveSupplierMaterial([
        'supplier_id' => (string) $supplierId,
        'supplier_material_code' => 'A-INVALID',
        'supplier_material_name' => '无效币种材料',
        'currency_code' => 'ZZZ',
    ]),
    422,
    'Shared supplier material prices must use a code from the ISO 4217 catalog.'
);
$quoteId = $catalog->saveQuotation(1, [
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
materialTestExpectStatus(
    static fn () => $catalog->saveQuotation(1, [
        'material_id' => (string) $materialId,
        'supplier_material_id' => (string) $supplierMaterialId,
        'purchase_unit_price' => '10',
        'quotation_date' => '2026-03-01',
        'currency_code' => 'ZZZ',
    ]),
    422,
    'Project quotations must use a code from the ISO 4217 catalog.'
);
$catalog->setDefaultQuotation(1, $materialId, $quoteId);
materialTestCheck((int) $db->query('SELECT default_quotation_id FROM Materials')->fetchColumn() === $quoteId, 'Default quotation selection must persist explicitly.');

$catalog->saveQuotation(1, [
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
    static fn () => $catalog->saveQuotation(1, [
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
    static fn () => $catalog->saveCategory(1, [
        'category_id' => (string) $rootCategoryId,
        'category_name' => '装修材料',
        'parent_category_id' => (string) $childCategoryId,
    ]),
    422,
    'Category cycles must be rejected.'
);
materialTestExpectStatus(
    static fn () => $catalog->deleteCategory(1, $childCategoryId),
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
$auxId = $catalog->saveAuxiliary(1, ['aux_name' => '填缝剂', 'unit' => 'kg']);
materialTestCheck((int) $db->query('SELECT COUNT(*) FROM Auxiliary_Materials WHERE aux_id = ' . $auxId)->fetchColumn() === 1, 'Auxiliary materials must be manageable.');
$projectTwoAuxId = $catalog->saveAuxiliary(2, ['aux_name' => '填缝剂', 'unit' => 'kg']);
materialTestCheck(
    count($catalog->auxiliaryData(1)) === 1
        && count($catalog->auxiliaryData(2)) === 1
        && $projectTwoAuxId !== $auxId,
    'Auxiliary material catalogs must be independent by project.'
);

$otherProjectCategoryId = $catalog->saveCategory(2, ['category_name' => '装修材料']);
$otherProjectMaterialId = $catalog->saveMaterial(2, [
    'material_code' => 'FL-001',
    'material_name' => '项目二地砖',
    'unit' => 'm2',
    'category_id' => (string) $otherProjectCategoryId,
]);
materialTestCheck(
    count($catalog->materialData(1)['materials']) === 1
        && count($catalog->materialData(2)['materials']) === 1
        && $catalog->materialData(1)['materials'][0]['material_name'] === '地砖'
        && $catalog->materialData(2)['materials'][0]['material_name'] === '项目二地砖',
    'Material catalogs and duplicate material codes must remain independent by project.'
);
materialTestExpectStatus(
    static fn () => $catalog->saveMaterial(1, [
        'material_name' => '跨项目分类材料',
        'unit' => 'm2',
        'category_id' => (string) $otherProjectCategoryId,
    ]),
    404,
    'Materials must not reference another project’s category.'
);
materialTestExpectStatus(
    static fn () => $catalog->saveMaterial(1, [
        'material_id' => (string) $otherProjectMaterialId,
        'material_name' => '越权编辑',
        'unit' => 'm2',
    ]),
    404,
    'Material updates must reject IDs from another project.'
);
// Duplicate material codes inside one project must fail with a clear business
// error (409), not an unhandled database exception; other projects stay free
// to reuse the same code (covered above).
materialTestExpectStatus(
    static fn () => $catalog->saveMaterial(1, [
        'material_code' => 'FL-001',
        'material_name' => '重复编码新材料',
        'unit' => 'm2',
    ]),
    409,
    'Creating a material with a code already used in the same project must return 409.'
);
$secondMaterialId = $catalog->saveMaterial(1, [
    'material_code' => 'FL-002',
    'material_name' => '墙砖',
    'unit' => 'm2',
]);
materialTestExpectStatus(
    static fn () => $catalog->saveMaterial(1, [
        'material_id' => (string) $secondMaterialId,
        'material_code' => 'FL-001',
        'material_name' => '墙砖',
        'unit' => 'm2',
    ]),
    409,
    'Renaming a material code onto another material in the same project must return 409.'
);
materialTestCheck(
    $catalog->saveMaterial(1, [
        'material_id' => (string) $materialId,
        'material_code' => 'FL-001',
        'material_name' => '地砖',
        'unit' => 'm2',
        'specifications' => '600×600',
    ]) === $materialId,
    'Updating a material while keeping its own code must still succeed.'
);

// Extra project-one categories and materials so the Excel export can be filtered.
$lightingCategoryId = $catalog->saveCategory(1, ['category_name' => '灯具', 'sort_order' => '20']);
$plumbingCategoryId = $catalog->saveCategory(1, ['category_name' => '水电', 'sort_order' => '30']);
$catalog->saveMaterial(1, [
    'material_code' => 'DG-001', 'material_name' => '筒灯', 'unit' => '个',
    'category_id' => (string) $lightingCategoryId, 'specifications' => '9W 暖光',
]);
$catalog->saveMaterial(1, [
    'material_code' => 'DG-002', 'material_name' => '射灯', 'unit' => '个',
    'category_id' => (string) $lightingCategoryId,
]);
$catalog->saveMaterial(1, [
    'material_code' => 'SD-001', 'material_name' => 'PVC 线管', 'unit' => 'm',
    'category_id' => (string) $plumbingCategoryId,
]);

$tenderService = new \ProjectO\Modules\MaterialManagement\TenderListService($db);
$tenderId = $tenderService->save(1, [
    'project_id' => '2',
    'material_id' => (string) $materialId,
    'tender_quantity' => '12.50',
    'tender_unit_price' => '25.75',
    'currency_code' => 'etb',
]);
materialTestCheck(
    count($tenderService->items(1)) === 1
        && $tenderService->items(1)[0]['project_id'] === 1
        && $tenderService->items(1)[0]['currency_code'] === 'ETB'
        && $tenderService->items(2) === [],
    'Tender records must use server project context, preserve currency and remain isolated.'
);
$otherTenderId = $tenderService->save(2, [
    'material_id' => (string) $otherProjectMaterialId,
    'tender_quantity' => '3',
    'tender_unit_price' => '8.25',
]);
materialTestCheck(
    count($tenderService->items(2)) === 1
        && $tenderService->items(2)[0]['tender_item_id'] === $otherTenderId
        && $tenderService->items(2)[0]['currency_code'] === 'CNY',
    'Project two should only see its own tender items.'
);
materialTestExpectStatus(
    static fn () => $tenderService->save(1, [
        'material_id' => (string) $materialId,
        'tender_quantity' => '1',
        'tender_unit_price' => '1',
        'currency_code' => 'ZZZ',
    ]),
    422,
    'Tender items must reject currency codes outside the ISO 4217 catalog.'
);
materialTestExpectStatus(
    static fn () => $tenderService->save(1, [
        'material_id' => (string) $otherProjectMaterialId,
        'tender_quantity' => '1',
        'tender_unit_price' => '1',
    ]),
    404,
    'Tender items must reject materials owned by another project.'
);
materialTestExpectStatus(
    static fn () => $tenderService->save(2, [
        'tender_item_id' => (string) $tenderId,
        'material_id' => (string) $otherProjectMaterialId,
        'tender_quantity' => '1',
        'tender_unit_price' => '1',
    ]),
    404,
    'Tender item updates must reject item IDs owned by another project.'
);
materialTestExpectStatus(
    static fn () => $tenderService->save(1, [
        'material_id' => (string) $materialId,
        'tender_quantity' => '1.234',
        'tender_unit_price' => '1',
    ]),
    422,
    'Tender quantities must enforce the legacy two-decimal precision.'
);

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
materialTestExpectStatus(
    static fn () => $tenderService->delete(2, $tenderId),
    404,
    'Tender item deletion must be scoped to its owning project.'
);
$tenderService->delete(1, $tenderId);
$tenderService->delete(2, $otherTenderId);
$estimateId = $projectMaterials->saveEstimate(1, [
    'building_id' => (string) $buildingId,
    'floor_id' => (string) $floorId,
    'room_id' => (string) $roomId,
    'material_id' => (string) $materialId,
    'estimated_quantity' => '12.3456',
    'remarks' => '首层试算',
]);
materialTestCheck((string) $projectMaterials->estimateData(1)[0]['estimated_quantity'] === '12.3456', 'Estimate quantities must preserve decimal precision without cost calculations.');
$estimateRows = $projectMaterials->estimateData(1);
materialTestCheck(
    (string) $estimateRows[0]['floor_name'] === '首层'
        && array_key_exists('category_name', $estimateRows[0])
        && array_key_exists('category_id', $estimateRows[0]),
    'Estimate rows must expose floor names and material category fields for record grouping and labels.'
);
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
    'material_id' => (string) $materialId,
    'supplier_id' => (string) $supplierId,
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
$projectMaterials->saveSample(1, 1, [
    'sample_id' => (string) $sampleId,
    'material_id' => (string) $materialId,
    'supplier_id' => (string) $supplierId,
    'sample_quantity' => '2',
    'color' => '暖灰',
    'document_status' => 'submitted',
    'sent_status' => 'partially_sent',
    'sent_date' => '2026-03-03',
    'owner_approval_status' => 'approved',
    'owner_approval_date' => '2026-03-04',
    'location_type' => 'floor',
    'location_id' => (string) $floorId,
    'remarks' => '技术更新不应清除商业关联',
]);
materialTestCheck(
    (int) $db->query("SELECT quotation_id FROM Material_Samples WHERE sample_id = {$sampleId}")->fetchColumn() === $quoteId,
    'Technical sample updates without quotation fields must preserve existing commercial associations.'
);
materialTestExpectStatus(
    static fn () => $projectMaterials->saveSample(2, 1, [
        'material_id' => (string) $materialId,
        'supplier_id' => (string) $supplierId,
        'quotation_id' => (string) $quoteId,
        'location_type' => 'floor',
        'location_id' => (string) $floorId,
    ]),
    404,
    'Sample locations must not cross project boundaries.'
);
$db->exec("UPDATE Material_Samples SET owner_approval_status = '已审批通过' WHERE sample_id = {$sampleId}");
$samples = $projectMaterials->listSamples(1);
materialTestCheck($samples[0]['location_name'] === 'A栋已更新 首层', 'Samples must show the selected project floor by name.');
materialTestCheck($samples[0]['owner_approval_status'] === 'approved', 'The legacy approved label must be normalized for display.');
$spaceArchiveResult = $spaceService->deleteOrArchive(1, 'floor', $floorId, 1);
materialTestCheck($spaceArchiveResult === 'archived', 'A floor used by a sample should be archived.');
materialTestCheck(
    $projectMaterials->locations(1)['floors'] === []
        && $projectMaterials->estimateChoices(1)['floors'] === [],
    'Archived floors must no longer be selectable for new sample or estimate records.'
);
materialTestExpectStatus(
    static fn () => $projectMaterials->saveSample(1, 1, [
        'material_id' => (string) $materialId,
        'supplier_id' => (string) $supplierId,
        'quotation_id' => (string) $quoteId,
        'location_type' => 'floor',
        'location_id' => (string) $floorId,
    ]),
    404,
    'Sample writes must reject archived locations.'
);
materialTestCheck(
    $projectMaterials->listSamples(1)[0]['location_name'] === 'A栋已更新 首层',
    'Archiving a referenced space must retain sample history and its recorded location.'
);
$projectMaterials->addImage(1, $sampleId, 1, 'samples/1/' . $sampleId . '/test.png', 'test.png', 'image/png', 123);
materialTestExpectStatus(
    static fn () => $catalog->deleteQuotation(1, $quoteId),
    409,
    'Quotations referenced by samples must not be deleted.'
);
$projectMaterials->archiveSample(1, $sampleId, 1);
materialTestCheck($projectMaterials->listSamples(1) === [], 'Archived samples must be excluded from active tracking.');
materialTestCheck((int) $db->query('SELECT COUNT(*) FROM Material_Sample_Images')->fetchColumn() === 1, 'Archiving a sample must retain its images.');
$unquotedSampleId = $projectMaterials->saveSample(1, 1, [
    'material_id' => (string) $materialId,
    'supplier_id' => (string) $supplierId,
    'sample_quantity' => '1',
    'location_type' => 'building',
    'location_id' => (string) $buildingId,
]);
$unquotedSamples = $projectMaterials->listSamples(1);
materialTestCheck(
    count($unquotedSamples) === 1
        && (int) $unquotedSamples[0]['sample_id'] === $unquotedSampleId
        && $unquotedSamples[0]['quotation_id'] === null
        && $unquotedSamples[0]['material_name'] === '地砖'
        && $unquotedSamples[0]['supplier_name'] === '供应商甲',
    'A sample should be trackable by material and supplier before a quotation exists.'
);
$otherMaterialId = $catalog->saveMaterial(1, [
    'material_name' => '墙砖',
    'unit' => 'm2',
]);
materialTestExpectStatus(
    static fn () => $projectMaterials->saveSample(1, 1, [
        'material_id' => (string) $otherMaterialId,
        'supplier_id' => (string) $supplierId,
        'quotation_id' => (string) $quoteId,
        'location_type' => 'building',
        'location_id' => (string) $buildingId,
    ]),
    422,
    'A quotation must match the sample material.'
);
$linkedSampleId = $projectMaterials->saveSample(1, 1, [
    'material_id' => (string) $materialId,
    'supplier_id' => (string) $supplierId,
    'quotation_id' => (string) $quoteId,
    'color' => '暖灰',
    'sample_quantity' => '1',
    'location_type' => 'building',
    'location_id' => (string) $buildingId,
]);
$db->exec("UPDATE Material_Samples SET sent_status = 'sent', owner_approval_status = 'approved' WHERE sample_id = {$linkedSampleId}");

$_SESSION = ['user_id' => 1, 'username' => 'admin', 'active_project_id' => 1];
$_SERVER = [];
$plugin = new MaterialManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/material-management')
);
$materialPage = $plugin->dispatch('materials', []);
materialTestCheck($materialPage->type === 'view' && is_file((string) $materialPage->viewPath), 'Global material catalog should not require an active project.');
$samplePage = $plugin->dispatch('samples', []);
$sampleTestHtml = renderMaterialTestView($samplePage);
materialTestCheck(
    substr_count($sampleTestHtml, '<!doctype html>') === 1
        && substr_count($sampleTestHtml, '<nav class="module-nav"') === 1
        && substr_count($sampleTestHtml, '<main class="container">') === 1,
    'Sample tracking page must render its layout, navigation, and page shell exactly once.'
);

materialTestCheck(
    str_contains($sampleTestHtml, 'id="samples-table"')
        && str_contains($sampleTestHtml, 'value="material.sample.export"')
        && str_contains($sampleTestHtml, 'data-dialog-open="sample-create"')
        && str_contains($sampleTestHtml, 'data-dialog-open="sample-edit-' . $linkedSampleId . '"'),
    'Sample tracking should render its table, Excel export, and create/edit dialogs.'
);

preg_match_all('/\sid="([^"]+)"/', $sampleTestHtml, $sampleIdMatches);
materialTestCheck(
    count($sampleIdMatches[1]) === count(array_unique($sampleIdMatches[1])),
    'Sample tracking page markup must not repeat element IDs.'
);
materialTestCheck(
    str_contains($sampleTestHtml, 'status-badge is-green')
        && str_contains($sampleTestHtml, 'status-badge is-purple')
        && str_contains($sampleTestHtml, 'status-badge is-blue')
        && str_contains($sampleTestHtml, 'status-badge is-orange')
        && str_contains($sampleTestHtml, 'status-badge is-amber'),
    'Sample tracking should render status columns as colored badges.'
);
materialTestCheck(
    substr_count($sampleTestHtml, '<dialog class="edit-dialog') === count($samplePage->data['samples']) * 2 + 1,
    'Sample tracking should render create, edit and delete dialogs for each sample.'
);
materialTestCheck(
    str_contains($sampleTestHtml, '<th scope="col">使用位置</th>')
        && str_contains($sampleTestHtml, 'data-dialog-open="sample-delete-' . $linkedSampleId . '"')
        && substr_count($sampleTestHtml, '确认删除') === count($samplePage->data['samples'])
        && !str_contains($sampleTestHtml, '>归档</button>'),
    'Samples must use the 使用位置 column and a 删除 action with a confirmation dialog.'
);
materialTestCheck(
    str_contains($sampleTestHtml, 'A栋已更新')
        && !str_contains($sampleTestHtml, 'A 1F'),
    'Sample locations must show building and floor names instead of their numbers.'
);
$sampleExport = $plugin->dispatch('sample.export', []);
materialTestCheck(
    $sampleExport->type === 'download'
        && ($sampleExport->data['content_type'] ?? '') === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        && preg_match('/^材料样品信息表_\d{8}_\d+\.xlsx$/u', (string) ($sampleExport->data['filename'] ?? '')) === 1,
    'Sample export should produce an .xlsx download response with a daily sequence number.'
);
$sampleExportSequence = (int) preg_replace(
    '/^材料样品信息表_\d{8}_(\d+)\.xlsx$/u',
    '$1',
    (string) $sampleExport->data['filename']
);
$registryExport = $registry->dispatch('POST', 'material.sample.export', []);
materialTestCheck(
    $registryExport instanceof PluginResponse && $registryExport->type === 'download',
    'Sample export must be registered as a plugin route in its manifest.'
);
$registryExportSequence = (int) preg_replace(
    '/^材料样品信息表_\d{8}_(\d+)\.xlsx$/u',
    '$1',
    (string) ($registryExport->data['filename'] ?? '')
);
materialTestCheck(
    $sampleExportSequence > 0 && $registryExportSequence === $sampleExportSequence + 1,
    'Export file names must use a daily sequence number.'
);
$sampleSheetXml = materialTestXlsxSheet($sampleExport);
$sampleStylesXml = materialTestXlsxPart($sampleExport, 'xl/styles.xml');
materialTestCheck(
    substr_count($sampleStylesXml, 'wrapText="1"') >= 2,
    'Exported cells must wrap long text inside the fixed column widths.'
);
materialTestCheck(
    str_contains($sampleStylesXml, 'horizontal="center"')
        && str_contains($sampleSheetXml, 'ht="30"'),
    'The report title row must be taller and horizontally centered.'
);
materialTestCheck(
    str_contains($sampleSheetXml, 'Project One材料样品信息追踪表')
        && str_contains($sampleSheetXml, '导出时间: ')
        && str_contains($sampleSheetXml, '记录总数: ' . count($samplePage->data['samples']))
        && str_contains($sampleSheetXml, '内容统计: 总样品数量 2')
        && str_contains($sampleSheetXml, '已送样品数量 1')
        && str_contains($sampleSheetXml, '已审批样品数量 1')
        && str_contains($sampleSheetXml, '材料代号')
        && str_contains($sampleSheetXml, '适用范围')
        && str_contains($sampleSheetXml, '样品图片')
        && str_contains($sampleSheetXml, '品牌')
        && str_contains($sampleSheetXml, '示例品牌')
        && str_contains($sampleSheetXml, 'A1:N1')
        && str_contains($sampleSheetXml, '地砖')
        && str_contains($sampleSheetXml, 'A栋已更新')
        && str_contains($sampleSheetXml, '供应商甲')
        && str_contains($sampleSheetXml, '未送')
        && str_contains($sampleSheetXml, '█ 暖灰')
        && !str_contains($sampleSheetXml, '报价单价')
        && !str_contains($sampleSheetXml, 'EEU')
        && substr_count($sampleSheetXml, '<row r=') === count($samplePage->data['samples']) + 6,
    'Exported sample rows should mirror the legacy 样品信息追踪表 layout.'
);

$previousMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['csrf_token' => Support::csrfToken(), 'action' => 'material.sample.export'];
ob_start();
(new \ProjectO\Application($db))->run();
$downloadResponse = (string) ob_get_clean();
$_POST = [];
$_SERVER['REQUEST_METHOD'] = $previousMethod;
materialTestCheck(
    str_starts_with($downloadResponse, 'PK'),
    'Sample export must be reachable through the application POST route.'
);
materialTestCheck($samplePage->type === 'view' && is_file((string) $samplePage->viewPath), 'Sample tracking should resolve its plugin view.');
$categorizedMaterialId = (int) $db->query("SELECT material_id FROM Materials WHERE project_id = 1 AND material_code = 'DG-001'")->fetchColumn();
$projectMaterials->saveEstimate(1, [
    'building_id' => (string) $buildingId,
    'material_id' => (string) $categorizedMaterialId,
    'estimated_quantity' => '2',
    'remarks' => '灯具测算',
]);
materialTestCheck(
    (string) $projectMaterials->estimateData(1)[0]['category_name'] === '灯具',
    'Estimate rows must resolve the material category through the project-scoped category table.'
);
$estimatePage = $plugin->dispatch('estimates', []);
materialTestCheck($estimatePage->type === 'view' && is_file((string) $estimatePage->viewPath), 'Material estimates should resolve their plugin view.');
$tenderPage = $plugin->dispatch('tender-list', []);
materialTestCheck($tenderPage->type === 'view' && is_file((string) $tenderPage->viewPath), 'Tender lists should resolve their plugin view.');
foreach ([
    [$materialPage, '材料管理'],
    [$plugin->dispatch('quotations', []), '材料报价'],
    [$samplePage, '样品跟踪'],
    [$estimatePage, '材料测算明细'],
    [$tenderPage, '招标清单'],
] as [$pageResponse, $expectedHeading]) {
    materialTestCheck(
        str_contains(renderMaterialTestView($pageResponse), $expectedHeading),
        "Material-management page should render its expected heading: {$expectedHeading}."
    );
}
$estimateHtml = renderMaterialTestView($estimatePage);
materialTestCheck(
    str_contains($estimateHtml, '快速录入')
        && str_contains($estimateHtml, 'data-estimate-add')
        && str_contains($estimateHtml, 'data-combo')
        && str_contains($estimateHtml, 'data-combo-select')
        && str_contains($estimateHtml, 'data-estimate-filter')
        && str_contains($estimateHtml, 'data-ledger-group')
        && str_contains($estimateHtml, 'A · A栋已更新')
        && str_contains($estimateHtml, '1F · 首层')
        && str_contains($estimateHtml, 'href="?page=estimates&amp;group=category"'),
    'Estimates page must render the quick-entry material picker and group records by building and floor.'
);
$categoryEstimatePage = $plugin->dispatch('estimates', ['group' => 'category']);
materialTestCheck(
    $categoryEstimatePage->type === 'view'
        && ($categoryEstimatePage->data['group'] ?? '') === 'category',
    'The estimates page must accept a material-category grouping mode.'
);
$categoryEstimateHtml = renderMaterialTestView($categoryEstimatePage);
materialTestCheck(
    str_contains($categoryEstimateHtml, 'class="ledger-group-title">灯具<')
        && str_contains($categoryEstimateHtml, 'href="?page=estimates&amp;group=space"'),
    'Category grouping must show material categories and link back to space grouping.'
);
$tenderHtml = renderMaterialTestView($tenderPage);
materialTestCheck(
    str_contains($tenderHtml, 'CNY - 人民币')
        && str_contains($tenderHtml, 'value="CNY" selected'),
    'Tender forms should default to CNY and offer standardized currency choices.'
);

$_SERVER['REQUEST_METHOD'] = 'GET';
foreach ([
    'materials' => '材料管理',
    'quotations' => '材料报价',
    'samples' => '样品跟踪',
    'tender-list' => '招标清单',
] as $page => $heading) {
    $_GET = ['page' => $page];
    http_response_code(200);
    ob_start();
    (new \ProjectO\Application($db))->run();
    $html = (string) ob_get_clean();
    materialTestCheck(
        http_response_code() === 200 && str_contains($html, $heading),
        "Application must render the {$page} page data without errors."
    );
    if ($page === 'materials') {
        materialTestCheck(
            str_contains($html, 'href="?page=projects">项目首页</a>')
                && str_contains($html, 'href="?page=quotations">报价</a>'),
            'Material pages should link to the project home and all authorized material pages.'
        );
    }
    if ($page === 'materials') {
        materialTestCheck(
            str_contains($html, 'id="material-search"')
            && str_contains($html, 'id="material-category"')
            && str_contains($html, 'class="report-table"')
            && str_contains($html, 'data-dialog-open="material-edit-')
            && str_contains($html, 'id="material-export-form"')
            && str_contains($html, 'value="material.export"')
            && str_contains($html, 'id="material-category" name="category_id" form="material-export-form"'),
            'Materials page should render its report filters, table, edit dialog and category-aware Excel export.'
        );
    }
}

// Material catalogue Excel export: all rows, or only the category chosen in the
// list filter; the file name carries the project, category and date.
$allMaterialsExport = $plugin->dispatch('material.export', []);
materialTestCheck(
    $allMaterialsExport->type === 'download'
        && ($allMaterialsExport->data['content_type'] ?? '') === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        && preg_match('/^Project One材料_全部_\d{8}\.xlsx$/u', (string) ($allMaterialsExport->data['filename'] ?? '')) === 1,
    'Material export without a category must download an all-category workbook named after the project and date.'
);
$allMaterialsSheet = materialTestXlsxSheet($allMaterialsExport);
materialTestCheck(
    str_contains($allMaterialsSheet, 'Project One材料清单')
        && str_contains($allMaterialsSheet, '导出范围: 全部材料')
        && str_contains($allMaterialsSheet, '序号')
        && str_contains($allMaterialsSheet, '材料代号')
        && str_contains($allMaterialsSheet, '材料名称')
        && str_contains($allMaterialsSheet, '单位')
        && str_contains($allMaterialsSheet, '分类')
        && str_contains($allMaterialsSheet, '规格')
        && str_contains($allMaterialsSheet, '详细说明')
        && str_contains($allMaterialsSheet, '地砖')
        && str_contains($allMaterialsSheet, '筒灯')
        && str_contains($allMaterialsSheet, '射灯')
        && str_contains($allMaterialsSheet, 'PVC 线管')
        && !str_contains($allMaterialsSheet, '项目二地砖')
        && str_contains($allMaterialsSheet, '<col min="3" max="3" width="28" customWidth="1"/>')
        && str_contains($allMaterialsSheet, '<col min="7" max="7" width="45" customWidth="1"/>')
        && substr_count($allMaterialsSheet, '<row r=') === 6 + 6,
    'The all-category export must contain every current-project material and no other project rows.'
);
$lightingExport = $plugin->dispatch('material.export', ['category_id' => (string) $lightingCategoryId]);
materialTestCheck(
    $lightingExport->type === 'download'
        && preg_match('/^Project One材料_灯具_\d{8}\.xlsx$/u', (string) ($lightingExport->data['filename'] ?? '')) === 1,
    'Selecting a category must put its name into the export file name.'
);
$lightingSheet = materialTestXlsxSheet($lightingExport);
materialTestCheck(
    str_contains($lightingSheet, '导出范围: 分类：灯具')
        && str_contains($lightingSheet, '筒灯')
        && str_contains($lightingSheet, '射灯')
        && !str_contains($lightingSheet, '地砖')
        && !str_contains($lightingSheet, 'PVC 线管')
        && substr_count($lightingSheet, '<row r=') === 2 + 6,
    'The category export must contain only the selected category materials.'
);
$registryMaterialExport = $registry->dispatch('POST', 'material.export', ['category_id' => (string) $lightingCategoryId]);
materialTestCheck(
    $registryMaterialExport instanceof PluginResponse && $registryMaterialExport->type === 'download',
    'Material export must be registered as a plugin route in its manifest.'
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('material.export', ['category_id' => (string) $otherProjectCategoryId]),
    404,
    'Material export must reject a category from another project.'
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('material.export', ['category_id' => 'abc']),
    422,
    'Material export must reject malformed category ids.'
);
unset($_SESSION['active_project_id']);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('material.export', []),
    409,
    'Material export must require an active project.'
);
$_SESSION['active_project_id'] = 1;
$db->exec(
    "DELETE FROM Role_Permissions
     WHERE permission_id = (SELECT permission_id FROM Permissions WHERE permission_name = 'view_materials')"
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('material.export', []),
    403,
    'Material export must require the view_materials permission.'
);
$db->exec(
    "INSERT INTO Role_Permissions (role_id, permission_id)
     SELECT 1, permission_id FROM Permissions WHERE permission_name = 'view_materials'"
);

$db->exec(
    "DELETE FROM Role_Permissions
     WHERE permission_id = (SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_quotations')"
);
$sampleWithoutCommercialPermission = $plugin->dispatch('samples', []);
$linkedSampleWithoutCommercialDetails = array_values(array_filter(
    $sampleWithoutCommercialPermission->data['samples'],
    static fn (array $sample): bool => (int) $sample['sample_id'] === $linkedSampleId
));
materialTestCheck(
    $sampleWithoutCommercialPermission->type === 'view'
        && $sampleWithoutCommercialPermission->data['choices']['quotations'] === []
        && $sampleWithoutCommercialPermission->data['canViewQuotations'] === false
        && count($linkedSampleWithoutCommercialDetails) === 1
        && $linkedSampleWithoutCommercialDetails[0]['purchase_unit_price'] === null
        && !str_contains(renderMaterialTestView($sampleWithoutCommercialPermission), '商业报价：'),
    'Sample tracking should remain available without exposing commercial quotation data.'
);

$privacySheetXml = materialTestXlsxSheet($plugin->dispatch('sample.export', []));
materialTestCheck(
    !str_contains($privacySheetXml, '报价单价')
        && !str_contains($privacySheetXml, '报价日期')
        && !str_contains($privacySheetXml, '品牌')
        && !str_contains($privacySheetXml, 'CNY')
        && !str_contains($privacySheetXml, 'EEU'),
    'Sample export must omit commercial quotation columns without quotation permissions.'
);
$fakePngBytes = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    true
);
$drawingWorkbook = XlsxWorkbook::build('样品信息追踪表', ['材料代号', '样品图片'], [['M-001', '']], [
    'title' => '📦 EEU材料样品信息追踪表（测试项目）',
    'meta' => ['导出时间: 2026-01-01 00:00:00', '记录总数: 1'],
    'images' => [[
        'row' => 6,
        'column' => 1,
        'bytes' => (string) $fakePngBytes,
        'extension' => 'png',
        'hyperlink' => 'https://example.com/sample.png?a=1&b=2',
    ]],
    'fixedWidths' => [1 => 15.0],
]);
$drawingPath = (string) tempnam(sys_get_temp_dir(), 'material-drawing-');
file_put_contents($drawingPath, (string) $drawingWorkbook);
$drawingZip = new ZipArchive();
$drawingOpened = $drawingZip->open($drawingPath) === true;
materialTestCheck(
    $drawingOpened
        && $drawingZip->locateName('xl/drawings/drawing1.xml') !== false
        && $drawingZip->locateName('xl/media/image1.png') !== false
        && $drawingZip->locateName('xl/worksheets/_rels/sheet1.xml.rels') !== false,
    'Sample export should embed sample images as drawing parts.'
);
$drawingXml = (string) $drawingZip->getFromName('xl/drawings/drawing1.xml');
$drawingRels = (string) $drawingZip->getFromName('xl/drawings/_rels/drawing1.xml.rels');
$drawingContentTypes = (string) $drawingZip->getFromName('[Content_Types].xml');
$drawingSheetXml = (string) $drawingZip->getFromName('xl/worksheets/sheet1.xml');
$drawingZip->close();
@unlink($drawingPath);
materialTestCheck(
    str_contains($drawingXml, '<xdr:twoCellAnchor editAs="oneCell">')
        && str_contains($drawingXml, 'r:embed="rIdM0"')
        && str_contains($drawingRels, 'Target="../media/image1.png"')
        && str_contains($drawingRels, 'Target="https://example.com/sample.png?a=1&amp;b=2"')
        && str_contains($drawingContentTypes, 'Extension="png" ContentType="image/png"')
        && str_contains($drawingSheetXml, '<drawing r:id="rId1"/>')
        && str_contains($drawingSheetXml, '<mergeCell ref="A1:B1"/>'),
    'Embedded sample images must anchor inside their cell, link to the source, and register their media type.'
);
$fakeImageStorage = new class implements ObjectStorageInterface {
    public function isConfigured(): bool
    {
        return true;
    }

    public function upload(string $objectKey, string $filePath, string $contentType): void
    {
    }

    public function signedReadUrl(string $objectKey, string $contentType): string
    {
        return 'https://signed.example.test/' . $objectKey;
    }

    public function read(string $objectKey): string
    {
        return '';
    }

    public function delete(string $objectKey): void
    {
    }
};
$imageStoragePlugin = new MaterialManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/material-management', $fakeImageStorage)
);
$db->exec(
    "INSERT INTO Material_Sample_Images (sample_id, project_id, object_key, original_name, content_type, file_size, uploaded_by)
     VALUES ({$linkedSampleId}, 1, 'samples/1/{$linkedSampleId}/photo.png', 'photo.png', 'image/png', 2048, 1)"
);
$sampleImagesPage = $imageStoragePlugin->dispatch('samples', []);
$sampleImagesHtml = renderMaterialTestView($sampleImagesPage);
materialTestCheck(
    str_contains($sampleImagesHtml, 'data-dialog-open="sample-images-' . $linkedSampleId . '"')
        && str_contains($sampleImagesHtml, 'id="sample-images-' . $linkedSampleId . '"')
        && str_contains($sampleImagesHtml, '样品图片 · 地砖')
        && str_contains($sampleImagesHtml, '样品信息')
        && str_contains($sampleImagesHtml, 'page=sample-image'),
    'Sample thumbnails should open a detail dialog with the image and sample information below it.'
);

$unquotedPost = $plugin->dispatch('sample.save', [
    'material_id' => (string) $materialId,
    'supplier_id' => (string) $supplierId,
    'sample_quantity' => '1',
    'location' => 'building:' . $buildingId,
]);
materialTestCheck(
    $unquotedPost->type === 'redirect',
    'A user without quotation access should be able to register an unquoted sample.'
);
$quotationWriteDenied = false;
try {
    $plugin->dispatch('sample.save', [
        'material_id' => (string) $materialId,
        'supplier_id' => (string) $supplierId,
        'quotation_id' => (string) $quoteId,
        'sample_quantity' => '1',
        'location' => 'building:' . $buildingId,
    ]);
} catch (HttpException $exception) {
    $quotationWriteDenied = $exception->statusCode === 403;
}
materialTestCheck($quotationWriteDenied, 'Quotation association must require commercial quotation permissions.');
$quotationPermissionId = (int) $db->query(
    "SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_quotations'"
)->fetchColumn();
$rolePermissionInsert->execute(['permission_id' => $quotationPermissionId]);

$migrationKeys = array_keys($registry->migrations());
$spaceMigrationPosition = array_search('project.foundation/202611010004_project_spaces.sql', $migrationKeys, true);
$archiveMigrationPosition = array_search('project.foundation/202611010006_project_archiving.sql', $migrationKeys, true);
$materialMigrationPosition = array_search('material.management/202611010005_material_management.sql', $migrationKeys, true);
$sampleDecouplingMigrationPosition = array_search(
    'material.management/202611040001_decouple_samples_from_quotations.sql',
    $migrationKeys,
    true
);
$projectMaterialsAndTendersMigrationPosition = array_search(
    'material.management/202611050001_project_materials_and_tenders.sql',
    $migrationKeys,
    true
);
$tenderCurrencyDefaultMigrationPosition = array_search(
    'material.management/202611050002_tender_currency_default_cny.sql',
    $migrationKeys,
    true
);
materialTestCheck(
    $spaceMigrationPosition !== false && $archiveMigrationPosition !== false
        && $materialMigrationPosition !== false
        && $sampleDecouplingMigrationPosition !== false
        && $projectMaterialsAndTendersMigrationPosition !== false
        && $tenderCurrencyDefaultMigrationPosition !== false
        && $projectMaterialsAndTendersMigrationPosition > $sampleDecouplingMigrationPosition
        && $tenderCurrencyDefaultMigrationPosition > $projectMaterialsAndTendersMigrationPosition
        && $spaceMigrationPosition < $archiveMigrationPosition
        && $archiveMigrationPosition < $materialMigrationPosition
        && $materialMigrationPosition < $sampleDecouplingMigrationPosition,
    'Plugin migrations must follow declared dependencies.'
);

materialTestCheck(
    \ProjectO\CurrencyCatalog::supports('cny')
        && \ProjectO\CurrencyCatalog::supports('ETB')
        && !\ProjectO\CurrencyCatalog::supports('ZZZ')
        && count(\ProjectO\CurrencyCatalog::options()) === 181
        && \ProjectO\CurrencyCatalog::options()['CNY'] === 'CNY - 人民币',
    'Currency choices must use the ISO 4217 catalog and include the CNY label.'
);

$previousStorageAdapter = getenv('APP_OBJECT_STORAGE_ADAPTER');
putenv('APP_OBJECT_STORAGE_ADAPTER');
materialTestCheck(StorageFactory::fromEnvironment() === null, 'Object storage must remain optional when no adapter is configured.');
putenv('APP_OBJECT_STORAGE_ADAPTER=ProjectO\\Storage\\R2ObjectStorage');
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

// Shared image storage is configured in the document module settings; sample
// tracking only consumes it and links there instead of hosting its own form.
$unconfiguredPlugin = new MaterialManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/material-management')
);
$unconfiguredHtml = renderMaterialTestView($unconfiguredPlugin->dispatch('samples', []));
materialTestCheck(
    str_contains($unconfiguredHtml, '?page=document-settings')
        && !str_contains($unconfiguredHtml, 'sample-storage-config'),
    'Sample tracking must link to the document settings instead of hosting storage configuration.'
);
putenv('APP_R2_ACCOUNT_ID');
putenv('APP_R2_BUCKET');
putenv('APP_R2_ACCESS_KEY_ID');
putenv('APP_R2_SECRET_ACCESS_KEY');
$storageSettings = new CloudStorageSettings($db);
$storageSettings->save([
    'enabled' => '1',
    'account_id' => 'd8f4d2fa4b3c4d5e8f9012345678abcd',
    'bucket' => 'project-o-samples',
    'access_key_id' => 'r2-access-key-id-example',
    'secret_access_key' => 'r2-secret-access-key-example',
]);
$projectMaterials->addImage(
    1,
    $linkedSampleId,
    1,
    'samples/1/' . $linkedSampleId . '/storage-test.png',
    'storage-test.png',
    'image/png',
    456
);
$storagePlugin = new MaterialManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/material-management')
);
$storageHtml = renderMaterialTestView($storagePlugin->dispatch('samples', []));
if (class_exists(\Aws\S3\S3Client::class)) {
    materialTestCheck(
        str_contains($storageHtml, 'class="thumb"')
            && str_contains($storageHtml, 'page=sample-image')
            && !str_contains($storageHtml, 'r2.cloudflarestorage.com'),
        'Sample thumbnails must be served through the application instead of direct bucket URLs.'
    );
}
$db->exec("DELETE FROM System_Settings WHERE setting_key LIKE 'cloud_storage.%'");

// Sample thumbnails and fixed-height Excel images (legacy export layout).
$thumbPng = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    true
);
$thumbFile = tempnam(sys_get_temp_dir(), 'sample-thumb-');
materialTestCheck($thumbFile !== false && is_string($thumbPng), 'The thumbnail fixture should be created.');
file_put_contents((string) $thumbFile, (string) $thumbPng);
MaterialTestImageStorage::$fileUrl = 'file://' . $thumbFile;
$fakeImageStorage = new MaterialTestImageStorage();
$imagePlugin = new MaterialManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/material-management', $fakeImageStorage)
);
$thumbImageId = $projectMaterials->addImage(
    1,
    $linkedSampleId,
    1,
    'samples/1/' . $linkedSampleId . '/thumb.png',
    'thumb.png',
    'image/png',
    68
);
$thumbHtml = renderMaterialTestView($imagePlugin->dispatch('samples', []));
materialTestCheck(
    str_contains($thumbHtml, 'class="thumb"')
        && str_contains($thumbHtml, 'page=sample-image&amp;sample_id=' . $linkedSampleId . '&amp;image_id=' . $thumbImageId),
    'The sample list must render image thumbnails served by the application.'
);
$imageResponse = $imagePlugin->dispatch('sample.image.view', [
    'sample_id' => (string) $linkedSampleId,
    'image_id' => (string) $thumbImageId,
]);
materialTestCheck(
    $imageResponse->type === 'inline'
        && ($imageResponse->data['content_type'] ?? '') === 'image/png'
        && ($imageResponse->data['body'] ?? '') === $thumbPng
        && str_contains(
            (string) ($imageResponse->data['filename'] ?? ''),
            '样品图片-' . $linkedSampleId . '-' . $thumbImageId . '.png'
        ),
    'Sample images must be streamed inline with a sequential file name.'
);
$db->exec(
    "DELETE FROM Role_Permissions
     WHERE permission_id = (SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_samples')"
);
materialTestExpectStatus(
    static fn () => $imagePlugin->dispatch('sample.image.view', [
        'sample_id' => (string) $linkedSampleId,
        'image_id' => (string) $thumbImageId,
    ]),
    403,
    'Sample image delivery must enforce the sample view permission.'
);
$rolePermissionInsert->execute([
    'permission_id' => (int) $db->query(
        "SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_samples'"
    )->fetchColumn(),
]);
$_SESSION['active_project_id'] = 2;
materialTestExpectStatus(
    static fn () => $imagePlugin->dispatch('sample.image.view', [
        'sample_id' => (string) $linkedSampleId,
        'image_id' => (string) $thumbImageId,
    ]),
    404,
    'Sample images must not cross project boundaries.'
);
$_SESSION['active_project_id'] = 1;
materialTestExpectStatus(
    static fn () => $imagePlugin->dispatch('sample.image.view', [
        'sample_id' => (string) $linkedSampleId,
        'image_id' => '999999',
    ]),
    404,
    'Unknown sample image ids must be rejected.'
);
$unconfiguredImagePlugin = new MaterialManagementPlugin(
    new PluginContext($db, dirname(__DIR__) . '/modules/material-management')
);
materialTestExpectStatus(
    static fn () => $unconfiguredImagePlugin->dispatch('sample.image.view', [
        'sample_id' => (string) $linkedSampleId,
        'image_id' => (string) $thumbImageId,
    ]),
    503,
    'Sample image delivery must fail clearly when storage is unconfigured.'
);
$previousViewAdapter = getenv('APP_OBJECT_STORAGE_ADAPTER');
putenv('APP_OBJECT_STORAGE_ADAPTER=MaterialTestImageStorage');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [
    'page' => 'sample-image',
    'sample_id' => (string) $linkedSampleId,
    'image_id' => (string) $thumbImageId,
];
ob_start();
(new \ProjectO\Application($db))->run();
$inlineBody = (string) ob_get_clean();
materialTestCheck(
    http_response_code() === 200 && $inlineBody === $thumbPng,
    'The application must stream sample images inline through its image route.'
);
if ($previousViewAdapter === false) {
    putenv('APP_OBJECT_STORAGE_ADAPTER');
} else {
    putenv('APP_OBJECT_STORAGE_ADAPTER=' . $previousViewAdapter);
}
$_GET = [];
$thumbExport = $imagePlugin->dispatch('sample.export', []);
$thumbExportBody = $thumbExport->data['body'] ?? null;
materialTestCheck(
    $thumbExport->type === 'download' && is_string($thumbExportBody),
    'The sample export with images should return an xlsx download.'
);
$thumbExportPath = tempnam(sys_get_temp_dir(), 'sample-export-');
materialTestCheck($thumbExportPath !== false, 'The export fixture path should be created.');
file_put_contents((string) $thumbExportPath, (string) $thumbExportBody);
$thumbZip = new ZipArchive();
materialTestCheck(
    $thumbZip->open((string) $thumbExportPath) === true,
    'The image export should be a readable zip package.'
);
$thumbDrawing = (string) $thumbZip->getFromName('xl/drawings/drawing1.xml');
$thumbSheet = (string) $thumbZip->getFromName('xl/worksheets/sheet1.xml');
$thumbRels = (string) $thumbZip->getFromName('xl/drawings/_rels/drawing1.xml.rels');
$thumbMedia = $thumbZip->locateName('xl/media/image1.png') !== false;
$thumbZip->close();
@unlink((string) $thumbExportPath);
materialTestCheck(
    $thumbMedia
        && str_contains($thumbDrawing, 'cy="714375"')
        && str_contains($thumbSheet, 'ht="85"')
        && str_contains($thumbRels, 'page=sample-image'),
    'Exported sample images must embed at the legacy fixed height and link to the image route.'
);
@unlink((string) $thumbFile);

unset($_SESSION['active_project_id']);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('samples', []),
    409,
    'Sample pages must require a current project.'
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('sample.export', []),
    409,
    'Sample export must require a current project.'
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
materialTestExpectStatus(
    static fn () => $plugin->dispatch('sample.export', []),
    403,
    'Sample export must enforce server-side feature permissions.'
);

// Estimate record export: columns, search parity, permissions, route, audit.
$estimateExport = $plugin->dispatch('estimate.export', []);
materialTestCheck(
    $estimateExport->type === 'download'
        && ($estimateExport->data['content_type'] ?? '') === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        && preg_match('/^材料测算明细_Project One_\d{8}_\d+\.xlsx$/u', (string) ($estimateExport->data['filename'] ?? '')) === 1,
    'Estimate export should download an .xlsx named 材料测算明细_项目名_日期_当天序号.'
);
$estimateExportSheet = materialTestXlsxSheet($estimateExport);
$estimateRowCount = count($projectMaterials->estimateData(1));
materialTestCheck(
    str_contains($estimateExportSheet, '位置空间')
        && str_contains($estimateExportSheet, '材料编号')
        && str_contains($estimateExportSheet, '规格')
        && str_contains($estimateExportSheet, '600×600')
        && str_contains($estimateExportSheet, '9W 暖光')
        && str_contains($estimateExportSheet, '测算数量')
        && str_contains($estimateExportSheet, 'A栋已更新 · 1F · 首层')
        && str_contains($estimateExportSheet, '12.3456')
        && substr_count($estimateExportSheet, '<row r=') === $estimateRowCount + 6,
    'Estimate export must contain location space, material code, specification and quantity for every record.'
);
materialTestCheck(
    str_contains($estimateExportSheet, '<col min="2" max="2" width="30"')
        && str_contains($estimateExportSheet, '<col min="5" max="5" width="32"'),
    'Estimate export must keep the narrowed location column and the widened specification column.'
);
$estimateSearchExport = $plugin->dispatch('estimate.export', ['search' => '灯具']);
$estimateSearchSheet = materialTestXlsxSheet($estimateSearchExport);
materialTestCheck(
    str_contains($estimateSearchSheet, 'DG-001')
        && str_contains($estimateSearchSheet, '筒灯')
        && str_contains($estimateSearchSheet, '9W 暖光')
        && !str_contains($estimateSearchSheet, 'FL-001')
        && !str_contains($estimateSearchSheet, '地砖')
        && str_contains($estimateSearchSheet, '导出范围: 搜索「灯具」匹配的测算记录'),
    'Estimate export must follow the page search keyword and report the filtered scope.'
);
$estimateCaseExport = $plugin->dispatch('estimate.export', ['search' => '  灯具  ']);
materialTestCheck(
    materialTestXlsxSheet($estimateCaseExport) === $estimateSearchSheet,
    'Estimate export search must normalise whitespace/case exactly like the page filter.'
);
$estimateNoMatchExport = $plugin->dispatch('estimate.export', ['search' => '不存在的关键词xyz']);
materialTestCheck(
    substr_count(materialTestXlsxSheet($estimateNoMatchExport), '<row r=') === 6
        && str_contains(materialTestXlsxSheet($estimateNoMatchExport), '记录总数: 0'),
    'Estimate export with no matching records must produce an empty, well-formed workbook.'
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('estimate.export', ['search' => str_repeat('测', 201)]),
    422,
    'Estimate export must reject over-long search keywords.'
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('estimate.export', ['search' => ['数组']]),
    422,
    'Estimate export must reject non-string search keywords.'
);
$registryEstimateExport = $registry->dispatch('POST', 'material.estimate.export', []);
materialTestCheck(
    $registryEstimateExport instanceof PluginResponse && $registryEstimateExport->type === 'download',
    'Estimate export must be registered as a plugin route in its manifest.'
);
$estimateAuditCount = (int) $db->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'material.estimate.export'")->fetchColumn();
materialTestCheck($estimateAuditCount >= 4, 'Estimate exports must write audit entries.');
$estimateExportHtml = renderMaterialTestView($plugin->dispatch('estimates', []));
materialTestCheck(
    str_contains($estimateExportHtml, 'id="estimate-export-form"')
        && str_contains($estimateExportHtml, 'value="material.estimate.export"')
        && str_contains($estimateExportHtml, 'name="search" form="estimate-export-form"')
        && str_contains($estimateExportHtml, '>导出 Excel</button>'),
    'Estimates page must render the export button beside the grouping buttons and bind the search field to it.'
);
$otherProjectEstimateExport = $plugin->dispatch('estimate.export', []);
$_SESSION['active_project_id'] = 2;
$projectTwoEstimateExport = $plugin->dispatch('estimate.export', []);
materialTestCheck(
    !str_contains(materialTestXlsxSheet($projectTwoEstimateExport), '首层试算')
        && str_contains(materialTestXlsxSheet($projectTwoEstimateExport), '记录总数: 0'),
    'Estimate export must only include records of the current project.'
);
$_SESSION['active_project_id'] = 1;
unset($_SESSION['active_project_id']);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('estimate.export', []),
    409,
    'Estimate export must require a current project.'
);
$_SESSION['active_project_id'] = 1;
$db->exec(
    "DELETE FROM Role_Permissions
     WHERE permission_id = (SELECT permission_id FROM Permissions WHERE permission_name = 'view_material_estimates')"
);
materialTestExpectStatus(
    static fn () => $plugin->dispatch('estimate.export', []),
    403,
    'Estimate export must enforce server-side feature permissions.'
);

echo "Material management tests passed.\n";
