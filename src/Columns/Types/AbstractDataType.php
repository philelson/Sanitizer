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
 * Date: 19/05/15
 * Time: 11:37
 */
namespace Pegasus\Application\Sanitizer\Columns\Types;

use Pegasus\Application\Sanitizer\Resource\Object;

abstract class AbstractDataType extends Object
{
    /**
     * Return the default data for this type of column.
     *
     * @return mixed
     */
    abstract function getBasicDefault();

    /**
     * This method returns an array of options
     *
     * @return array
     */
    public function option()
    {
        return array();
    }

    /**
     * Returns the column name
     *
     * @return string
     */
    public function getName()
    {
        return $this->getColumn();
    }

    /**
     * Returns true if the column exists in the table
     *
     * @return bool
     */
    public function exists()
    {
        return $this->getEngine()->columnExists($this->getTableName(), $this->getName());
    }

    /**
     * Returns the default value.
     * 1) Overridden in the config takes precedence
     * 2) Mock object is next priority
     * 3) Default type is the last priority
     *
     * @return mixed
     */
    public function getDefault()
    {
        if (null != parent::getDefault()) {
            return parent::getDefault();
        }

        if (null != $this->getMockModel()) {
            $modelName = $this->getMockModel();

            if (true == class_exists($modelName)) {
                $model = new $modelName();
                return $model->getRandomValue();
            }
        }

        return $this->getBasicDefault();
    }

    /**
     * This method returns a mock instance.
     *
     * @return string
     */
    public function getMockModel()
    {
        $className = parent::getMockModel();

        if (true == class_exists($className)) {
            return $className;
        }

        $className = "Pegasus\\Application\\Sanitizer\\Columns\\Mock\\".$className;

        if (true == class_exists($className)) {
            return $className;
        }

        return null;
    }
}