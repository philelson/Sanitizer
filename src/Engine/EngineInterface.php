<?php
/**
/**
 *
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
 * Time: 12:37
 */
namespace Pegasus\Application\Sanitizer\Engine;

interface EngineInterface
{
    /**
     * Returns a string representation of the engine being used. For instance 'mysql'
     *
     * @return mixed
     */
    public function getEngineName();

    /**
     * Returns true if the table name exists in the database
     *
     * @param  $tableName
     * @return bool if found, false otherwise
     * @throws FatalEngineException if an error occurred
     */
    public function tableExists($tableName);

    /**
     * Returns true if the column exists in the table
     *
     * @param  $tableName
     * @param  $columnName
     * @return bool if found, false otherwise
     * @throws FatalEngineException if an error occurred
     */
    public function columnExists($tableName, $columnName);

    /**
     * Returns the name of the primary key column
     *
     * @param  $tableName Is the name of the table to extract the primary key from
     * @return string primary key column name
     * @throws EngineException if the primary key was not found in the returned data.
     * @throws FatalEngineException  if an error occurred
     */
    public function getPrimaryKeyName($tableName);

    /**
     *  Log the error
     *
     * @param  null $query
     * @return mixed
     */
    public function logError($query=null);

    public function drop();

    public function create();

    public function useDb();

    public function source($fileName);

    public function dump($fileName);

    public function getDatabaseName();

    public function copyDown($config, $fileName=null);
}