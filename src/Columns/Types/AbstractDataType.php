<?php
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
 * Date: 19/05/15
 * Time: 11:37
 */
namespace Pegasus\Columns\Types;

use Pegasus\Engine\Engine;
use Pegasus\Resource\Object;

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

    public function getName()
    {
        return $this->getColumn();
    }

    public function exists()
    {
        return Engine::getInstance()->columnExists($this->getTableName(), $this->getName());
    }

    public function getDefault()
    {
        if(null != $this->getMockModel())
        {
            $modelName = $this->getMockModel();
            $model = new $modelName();
            return $model->getRandomValue();
        }
        return $this->getBasicDefault();
    }
}