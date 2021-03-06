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
 *
 * The basic flow is this:
 * <ul><li>App loads the config</li>
 * <li>App validates that the database can be connected to</li>
 * <li>App validates that the database exists</li>
 * <li>App validates all the tables are in the database which are specified in the config
 *      <ul><li>App also validates that the column is also in the table</li></ol>
 * </li>
 * <li>App then iterates over each table and sanitizes the data</li></ul>
 *
 * Once the application has finished the database will be in a sanitized state.
 *
 *
 * Date: 18/05/15
 * Time: 12:50
 *
 * http://symfony.com/doc/current/components/console/introduction.html#using-command-arguments
 */
namespace Pegasus\Application\Sanitizer;

use Pegasus\Application\Sanitizer\Table\Collection;
use Pegasus\Application\Sanitizer\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class Validation extends Sanitizer
{
    static $validator = null;

    /**
     * Default constructor.
     * Set validation to running
     */
    public function __construct()
    {
        $this->setValidationRunning();
        parent::__construct();
    }

    /**
     * Run the validation - does not sanitise
     *
     * @throws TableException
     */
    protected function sanitize()
    {
        $collection = new Collection($this->getEngine());
        $collection->getCollection($this); /* we just want to parse the config */
        $this->setValidationNotRunning();
    }

    /**
     * Configurage this instance from the command line
     */
    protected function configure()
    {
        $this
            ->setName('validate')
            ->setDescription('Validates the sanitizer configuration')
            ->addArgument(
                'engine',
                InputArgument::OPTIONAL,
                'Database Engine',
                'mysql'
            )
            ->addOption(
                'host',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Database Host',
                'localhost'
            )
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_REQUIRED,
                'Database Password'
            )
            ->addOption(
                'username',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Database User',
                'root'
            )
            ->addOption(
                'database',
                'db',
                InputOption::VALUE_OPTIONAL,
                'Database',
                'sanitizer'
            )
            ->addOption(
                'configuration',
                'co',
                InputOption::VALUE_OPTIONAL,
                'Database JSON Config File',
                'sanitize.json'
            )
            ->addOption(
                'mode',
                null,
                InputOption::VALUE_OPTIONAL,
                'Mode (sanitize, validate)',
                'sanitize'
            )
            ->addOption(
                'memory',
                null,
                InputOption::VALUE_OPTIONAL,
                'Memory - PHP format',
                '2048M'
            );
    }

    /**
     * Added for semantics.
     * Sets the running flag to true
     */
    protected function setValidationRunning()
    {
        $this->_satitisationRunning = true;
    }

    /**
     * Added for meaning
     * Sets the running flag to false
     */
    protected function setValidationNotRunning()
    {
        $this->_satitisationRunning = false;
        $this->purgePrintCache();
    }

    /**
     * Outputs initial information on the app.
     */
    protected function outputIntro()
    {
        $this->printLn("Config Validation Mode", 'notice');

        if (true == $this->getConfig()->getIsInDeveloperMode()
            || $this->getConfig()->getIsInDeveloperMode() == OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $this->printLn("App is in developer mode, therefore all output will be shown!", 'warning');
            $this->printLn("Verbosity ".$this->_output->getVerbosity(), 'warning');
        }
    }

    /**
     * We don't want to prompt the user, this app doesn't do any db changes.
     */
    protected function askPermissionToContinue()
    {
        /* do nothing */
    }

    /**
     * Purges the print cache
     */
    protected function purgePrintCache()
    {
        $table = new Table($this->_output);
        $table->setHeaders(array('Message', 'Level'));
        $rows = array();

        foreach ($this->_printCache as $item) {
            $message = $item['message'];
            $type = $item['type'];
            $rows[] = array($message, $type);
        }

        $table->setRows($rows);
        $table->render();
        $this->_printCache = array();
    }

    /**
     * Returns the app mode. There are two options.
     * Sanitize, Verify
     *
     * @return mixed
     */
    public function getMode()
    {
        return 'validate';
    }

    /**
     * Retuns a Singleton instance of the sanitizer
     *
     * @return null|Sanitizer
     */
    public static function getInstance()
    {
        if (null == self::$validator) {
            self::$validator = new Validation();
        }

        return self::$validator;
    }
}