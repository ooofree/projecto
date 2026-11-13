<?php
declare(strict_types=1);

use ProjectO\HttpException;
use ProjectO\Modules\ProjectFoundation\ProjectSpaceService;

require dirname(__DIR__) . '/vendor/autoload.php';

function spaceTestCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('CREATE TABLE Projects (project_id INTEGER PRIMARY KEY, project_name TEXT NOT NULL)');
$db->exec("INSERT INTO Projects VALUES (1, 'One'), (2, 'Two')");
$db->exec(
    "CREATE TABLE project_foundation_archive_records (
        archive_id INTEGER PRIMARY KEY AUTOINCREMENT,
        entity_type TEXT NOT NULL,
        entity_id INTEGER NOT NULL,
        project_id INTEGER NOT NULL REFERENCES Projects (project_id),
        archived_by INTEGER NOT NULL,
        archived_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (entity_type, entity_id)
    )"
);
$db->exec(
    "CREATE TABLE Buildings (
        building_id INTEGER PRIMARY KEY,
        project_id INTEGER NOT NULL REFERENCES Projects (project_id),
        building_number TEXT NOT NULL,
        building_name TEXT NOT NULL
    )"
);
$db->exec(
    "CREATE TABLE Building_Floors (
        floor_id INTEGER PRIMARY KEY,
        building_id INTEGER NOT NULL REFERENCES Buildings (building_id),
        floor_number TEXT NOT NULL,
        floor_name TEXT,
        usage_type TEXT,
        UNIQUE (floor_id, building_id)
    )"
);
$db->exec(
    "CREATE TABLE Construction_Areas (
        area_id INTEGER PRIMARY KEY,
        building_id INTEGER NOT NULL REFERENCES Buildings (building_id),
        area_name TEXT NOT NULL,
        description TEXT,
        UNIQUE (area_id, building_id)
    )"
);
$db->exec(
    "CREATE TABLE Rooms (
        room_id INTEGER PRIMARY KEY,
        building_id INTEGER NOT NULL,
        floor_id INTEGER NOT NULL,
        room_number TEXT NOT NULL,
        room_type TEXT,
        room_area REAL,
        room_height REAL,
        room_status TEXT,
        FOREIGN KEY (floor_id, building_id) REFERENCES Building_Floors (floor_id, building_id),
        UNIQUE (room_id, building_id, floor_id)
    )"
);
$db->exec(
    "CREATE TABLE Construction_Area_Rooms (
        area_room_id INTEGER PRIMARY KEY,
        room_id INTEGER NOT NULL,
        area_id INTEGER NOT NULL,
        floor_id INTEGER NOT NULL,
        building_id INTEGER NOT NULL,
        room_number TEXT NOT NULL,
        remarks TEXT,
        FOREIGN KEY (area_id, building_id) REFERENCES Construction_Areas (area_id, building_id),
        FOREIGN KEY (floor_id, building_id) REFERENCES Building_Floors (floor_id, building_id),
        FOREIGN KEY (room_id, building_id, floor_id) REFERENCES Rooms (room_id, building_id, floor_id)
    )"
);
$db->exec("INSERT INTO Buildings VALUES (10, 1, 'A', 'Tower A'), (20, 2, 'B', 'Tower B')");
$db->exec("INSERT INTO Building_Floors VALUES (11, 10, '01', 'Ground', 'office')");
$db->exec("INSERT INTO Construction_Areas VALUES (12, 10, 'Lobby', NULL)");
$db->exec("INSERT INTO Construction_Areas VALUES (15, 10, 'Sample area', NULL)");
$db->exec("INSERT INTO Rooms (room_id, building_id, floor_id, room_number, room_status) VALUES (13, 10, 11, '101', 'available')");
$db->exec("INSERT INTO Construction_Area_Rooms VALUES (14, 13, 12, 11, 10, '101', NULL)");
$db->exec('CREATE TABLE Sample_Location_Map (project_id INTEGER, sample_id INTEGER, location_type TEXT, location_id INTEGER)');
$db->exec("INSERT INTO Sample_Location_Map VALUES (1, 50, 'area', 15)");

$spaces = new ProjectSpaceService($db);
$listed = $spaces->list(1);
spaceTestCheck(count($listed) === 1 && count($listed[0]['rooms']) === 1, 'Spaces should be listed only for their project.');
$escape = [ProjectO\Support::class, 'escape'];
$renderSpacesView = static function (array $buildings, bool $archivedView) use ($escape): string {
    $currentProject = ['project_name' => 'Test project'];
    $canManage = true;
    $csrfToken = 'test-token';
    $title = '项目空间';
    $theme = 'light';
    $username = 'tester';
    $canManagePlatform = false;
    $flash = null;
    ob_start();
    require dirname(__DIR__) . '/modules/project-foundation/views/spaces.php';
    return (string) ob_get_clean();
};
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    $currentSpacesHtml = $renderSpacesView($listed, false);
    spaceTestCheck(
        str_contains($currentSpacesHtml, '新增房间') && str_contains($currentSpacesHtml, 'value="101"'),
        'The current-space view should render rooms returned by the service.'
    );
    spaceTestCheck(
        str_contains($currentSpacesHtml, 'space-tree')
            && str_contains($currentSpacesHtml, 'data-dialog-open="space-floor-create-10"')
            && str_contains($currentSpacesHtml, 'data-dialog-open="space-room-create-11"')
            && str_contains($currentSpacesHtml, 'id="space-room-edit-13"')
            && str_contains($currentSpacesHtml, 'id="space-floor-edit-11"'),
        'The spaces view should render a building/floor/room tree with in-node add and dialog edit.'
    );
    spaceTestCheck(
        !str_contains($currentSpacesHtml, '施工区域') && !str_contains($currentSpacesHtml, 'space-area'),
        'Construction areas are task-organization data and should not appear in the space ledger.'
    );
    $archivedSpacesHtml = $renderSpacesView($listed, true);
    spaceTestCheck(
        !str_contains($archivedSpacesHtml, '<dialog '),
        'The archive view should stay read-only and render no edit dialogs.'
    );
} finally {
    restore_error_handler();
}

spaceTestCheck(
    array_key_exists('is_archived', $listed[0]['rooms'][0])
        && (int) $listed[0]['rooms'][0]['is_archived'] === 0,
    'Active room rows should expose an is_archived flag for the space view.'
);
spaceTestCheck(
    $spaces->deleteOrArchive(1, 'building', 10, 7) === 'archived',
    'A building with children should be archived and retained.'
);
spaceTestCheck($spaces->list(1) === [], 'Archived buildings should be removed from the active space list.');
$archived = $spaces->list(1, true);
spaceTestCheck(
    count($archived) === 1 && (int) $archived[0]['is_archived'] === 1
        && count($archived[0]['floors']) === 1 && count($archived[0]['area_rooms']) === 1,
    'The archive view should keep the full space hierarchy accessible.'
);
try {
    $spaces->deleteOrArchive(1, 'building', 20, 7);
    throw new RuntimeException('Cross-project building deletion should be rejected.');
} catch (HttpException $exception) {
    spaceTestCheck($exception->statusCode === 404, 'Cross-project space deletion should return HTTP 404.');
}
$spaces->restore(1, 'building', 10);
spaceTestCheck(count($spaces->list(1)) === 1, 'Restoring a building should restore its accessible hierarchy.');

spaceTestCheck(
    $spaces->deleteOrArchive(1, 'floor', 11, 7) === 'archived',
    'A floor referenced by rooms should be archived rather than deleted.'
);
spaceTestCheck(
    $spaces->list(1)[0]['floors'] === [] && $spaces->list(1)[0]['rooms'] === [],
    'Archiving a floor should also hide dependent rooms from active space selection.'
);
$spaces->restore(1, 'floor', 11);
spaceTestCheck(count($spaces->list(1)[0]['rooms']) === 1, 'Restoring a floor should restore its room in the active hierarchy.');

spaceTestCheck(
    $spaces->deleteOrArchive(1, 'area', 12, 7) === 'archived'
        && $spaces->deleteOrArchive(1, 'room', 13, 7) === 'archived',
    'Area and room mappings should be retained by archiving their referenced entities.'
);
$archivedRooms = $spaces->list(1, true)[0]['rooms'];
spaceTestCheck(
    count($archivedRooms) === 1 && (int) $archivedRooms[0]['is_archived'] === 1,
    'Archived room rows should report is_archived in the archive view.'
);
$activeMappings = $spaces->list(1)[0]['area_rooms'];
$archivedMappings = $spaces->list(1, true)[0]['area_rooms'];
spaceTestCheck(
    $activeMappings === [] && count($archivedMappings) === 1,
    'Mappings should remain visible in the archive view but not active lists.'
);
spaceTestCheck(
    $spaces->deleteOrArchive(1, 'area', 15, 7) === 'archived',
    'Polymorphic sample-location references should prevent area deletion.'
);

echo "Project space service tests passed.\n";
