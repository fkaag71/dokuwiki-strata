<?php

use PHPUnit\Framework\TestCase;

final class SqliteJoinOrderTest extends TestCase
{
    public function testConjunctionPreservesBindingsWithOptionalValuesDuplicatesAndCaseDifferences(): void
    {
        global $conf, $ID;
        $conf['plugin']['strata']['default_dsn'] = 'sqlite::memory:';
        $conf['plugin']['strata']['scoped'] = 0;
        $ID = 'test';
        $triples = new helper_plugin_strata_triples();
        $triples->_db->query("INSERT INTO data VALUES
            ('item:a', 'kind', 'risk', 'test'),
            ('item:a', 'kind', 'risk', 'test'),
            ('ITEM:A', 'label', 'Alpha', 'test'),
            ('item:a', 'score', '6', 'test'),
            ('item:b', 'kind', 'risk', 'test'),
            ('item:b', 'label', 'Beta', 'test')");
        $query = [
            'type' => 'select', 'projection' => ['item', 'label', 'score'],
            'ordering' => [['variable' => 'label', 'direction' => 'asc']],
            'grouping' => false, 'considering' => [],
            'group' => [
                'type' => 'and',
                'lhs' => [
                    'type' => 'optional',
                    'lhs' => ['type' => 'triple', 'subject' => ['type' => 'variable', 'text' => 'item'], 'predicate' => ['type' => 'literal', 'text' => 'kind'], 'object' => ['type' => 'literal', 'text' => 'risk']],
                    'rhs' => ['type' => 'triple', 'subject' => ['type' => 'variable', 'text' => 'item'], 'predicate' => ['type' => 'literal', 'text' => 'score'], 'object' => ['type' => 'variable', 'text' => 'score']],
                ],
                'rhs' => ['type' => 'triple', 'subject' => ['type' => 'variable', 'text' => 'item'], 'predicate' => ['type' => 'literal', 'text' => 'label'], 'object' => ['type' => 'variable', 'text' => 'label']],
            ],
        ];

        $result = iterator_to_array($triples->queryRelations($query), false);

        self::assertSame([
            ['item' => ['item:a'], 'label' => ['Alpha'], 'score' => ['6']],
            ['item' => ['item:b'], 'label' => ['Beta'], 'score' => []],
        ], $result);
        [$sql] = (new strata_sql_generator($triples))->translate($query);
        self::assertStringContainsString(' CROSS JOIN ', $sql);
        self::assertStringContainsString(' LEFT OUTER JOIN ', $sql);
    }
}
