<?php
declare(strict_types=1);

$root = dirname(__DIR__);

/**
 * 收集 prepare(...) 调用中的字符串字面量（按 token 解析，避免跨语句误匹配）。
 *
 * @return list<array{file:string,line:int,sql:string}>
 */
function preparedLiterals(string $file): array
{
    $tokens = token_get_all((string) file_get_contents($file));
    $calls = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'prepare') {
            continue;
        }
        $sqlToken = null;
        for ($j = $i + 1; $j < $count; $j++) {
            $t = $tokens[$j];
            if (is_array($t) && $t[0] === T_WHITESPACE) {
                continue;
            }
            if ($t === '(') {
                continue;
            }
            if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
                $sqlToken = $t;
            }
            break;
        }
        if ($sqlToken !== null) {
            $calls[] = [
                'file' => $file,
                'line' => $sqlToken[2],
                'sql' => stripcslashes(substr($sqlToken[1], 1, -1)),
            ];
        }
    }

    return $calls;
}

$files = glob($root . '/modules/bom-costing/src/*.php') ?: [];
$failures = [];

foreach ($files as $file) {
    foreach (preparedLiterals($file) as $call) {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $call['sql'], $matches);
        $counts = array_count_values($matches[1]);
        foreach ($counts as $name => $occurrences) {
            if ($occurrences > 1) {
                $failures[] = sprintf(
                    '%s:%d 命名占位符 :%s 出现 %d 次（PDO 关闭模拟预处理时会抛 HY093，需拆成两端各自命名）',
                    basename($call['file']),
                    $call['line'],
                    $name,
                    $occurrences
                );
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "SQL 占位符检查失败：\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "SQL 占位符检查通过（" . count($files) . " 个文件）\n";
