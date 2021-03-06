<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 Philip Elson <phil@pegasus-commerce.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * Date: 18/05/15
 * Time: 12:42
 */
namespace Pegasus\Application\Sanitizer\Table\Tables;

use Pegasus\Application\Sanitizer\Columns\Types;
use Pegasus\Application\Sanitizer\Table\Exceptions\TableException;

class Flat extends AbstractTable
{
    const FIELD_DATA_TYPE = 'data_type';

    const FIELD_COLUMN = 'column';

    public static function getType()
    {
        return 'flat';
    }

    /**
     * Method to set the table data and have each class set up its own instance based on that data.
     *
     * @param  array $tableData
     * @return mixed
     * @throws TableException when column type could not be found.
     */
    function setTableData(array $tableData)
    {
        parent::setTableData($tableData);

        if (true == $this->doCommand()) {
            return true;
        }

        if (true == $this->skip($tableData)) {
            return false;
        }

        $this->loadColumnInstances($tableData);

        foreach ($this->getColumns() as $column) {

            if (false == $column->exists()) {
                $db = $this->getTerminalPrinter()->getConfig()->getDatabase()->getDatabaseName();
                $msg = "Column '{$column->getName()}' in table '{$this->getTableName()}' not found in database '{$db}'";
                throw new TableException($msg);
            }
        }
        return true;
    }

    private function loadColumnInstances($tableData)
    {
        foreach ($tableData as $columnData) {

            if (false == isset($columnData[self::FIELD_COLUMN])) {
                $data = $columnData;

                if (true == is_array($columnData)) {
                    $data = implode(',', $columnData);
                }

                $msg = "No column name could be found on table '{$this->getTableName()}' for data ".$data;
                throw new TableException($msg);
            }

            if (true == isset($columnData[self::FIELD_DATA_TYPE])) {
                $configDataType = $columnData[self::FIELD_DATA_TYPE];
                $this->addColumn($this->getInstanceFromType($configDataType, $columnData));
            }
        }
    }

    /**
     * Returns true to skip.
     *
     * @param  $tableData
     * @return bool
     */
    private function skip($tableData)
    {
        /* Flat tables are simple, if the array has now data then we are screwed. */
        if (0 == sizeof($tableData)) {
            $msg = "No columns to manipulate could be found for table '{$this->getTableName()}', skipping";
            $this->getTerminalPrinter()->printLn($msg, 'general');

            return true;
        }

        return false;
    }

    /**
     * Sanitizes in 1 of 2 modes, quick and everything else.
     * Quick changes every value with the table to a random selection - but they will all be the same.
     * otherwise each column has data set individually set.
     */
    public function sanitize()
    {
        $printer        = $this->getTerminalPrinter();
        $rowsEffected   = $this->hasExecutedCommand();

        if (false !== $rowsEffected) {
            return $rowsEffected;
        }

        $columns = $this->getColumnsForEngineQuery();

        if (true == $this->getIsQuickSanitisation()) {
            $printer->printLn("Sanitizing Flat {$this->getTableName()}", 'notice');
            $rows = $this->getEngine()->update($this->getTableName(), $columns);
            $printer->printLn("Sanitized Flat {$this->getTableName()}", 'notice');

            return $rows;

        } else {
            $printer->printLn("Sanitizing Flat {$this->getTableName()} ", 'notice');
            $rowsUpdated = 0;
            $rows = $this->getEngine()->select($this->getTableName(), $this->getSelectColumns());

            if (false == $rows) {
                $printer->printLn("No rows round for {$this->getTableName()} ", 'warning');
                return 0;
            }

            foreach ($rows as $row) {
                $rowSubset = $this->getColumnsForEngineQuery();
                $rowsUpdated += $this->getEngine()->update(
                    $this->getTableName(),
                    $rowSubset,
                    $this->getPrimaryKeyData($row)
                );
            }

            $printer->printLn("Sanitized Flat {$this->getTableName()} ", 'notice');

            return $rowsUpdated;
        }

        return 0;
    }

    private function getColumnsForEngineQuery()
    {
        $columns = array();

        foreach ($this->getColumns() as $column) {
            $columns[$column->getName()] = $column->getDefault();
        }

        return $columns;
    }
}
