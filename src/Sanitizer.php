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
 * <li>App validates all the tables are in the database which are specified
 * in the config
 *  <ul>
 *      <li>App also validates that the column is also in the table</li>
 *  </ul>
 * </li>
 * <li>App then iterates over each table and sanitizes the data</li></ul>
 *
 * Once the application has finished the database will be in a sanitized state.
 *
 *
 * Date: 18/05/15
 * Time: 12:50
 *
 * @category Pegasus_Utilities
 * @package  Sanitizer
 * @license  MIT
 * @link     http://pegasus-commerce.com
 * @author   Philip Elson <phil@pegasus-commerce.com>
 *
 * http://symfony.com/doc/current/components/console/introduction.html
 */
namespace Pegasus\Application\Sanitizer;

use Pegasus\Application\Sanitizer\Configuration\Config;
use Pegasus\Application\Sanitizer\Events\Observer\PostConditions;
use Pegasus\Application\Sanitizer\Events\Observer\PreConditions;
use Pegasus\Application\Sanitizer\Events\SimpleEvent;
use Pegasus\Application\Sanitizer\Resource\SanitizerException;
use Pegasus\Application\Sanitizer\IO\TerminalPrinter;
use Pegasus\Application\Sanitizer\Engine\EngineInterface;
use Pegasus\Application\Sanitizer\Configuration\Config as SanitizerConfig;
use Pegasus\Application\Sanitizer\Application;
use Pegasus\Application\Sanitizer\Engine\EngineFactory;
use Pegasus\Application\Sanitizer\Table\Collection as TableCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class Sanitizer extends Command implements TerminalPrinter
{
    /**
     * Defines the version of the application
     */
    const VERSION = '0.3.4.beta';

    /**
     * Default memory limit for this application is 1024M
     */
    const DEFAULT_MEMORY = '1024M';

    /**
     * Default memory not set value
     */
    const DEFAULT_MEMORY_NOT_SET = '0';

    /**
     * Application configuration instance
     *
     * @var null
     */
    public $config = null;

    /**
     * Console output instance
     *
     * @var OutputInterface
     */
    protected $_output = null;

    /**
     * Console input instance
     *
     * @var InputInterface
     */
    protected $_input = null;

    /**
     * Flag which identifies if the sanitisation is running. true if running.
     *
     * @var bool
     */
    protected $_satitisationRunning = false;

    /**
     * Print cache used mainly when the sanitisation is running.
     *
     * @var array
     */
    protected $_printCache = array();

    /**
     * Progress bar used to display the progress of sanitisation.
     *
     * @var ProgressBar
     */
    protected $_progressBar = null;

    /**
     * Singleton instance of Sanitizer
     *
     * @var Sanitizer
     */
    protected static $_sanitizer = null;

    /**
     * Instance of Logger
     *
     * @var Logger
     */
    protected $_log = null;

    /**
     * Event dispatcher
     *
     * @var null
     */
    protected $_eventDispatcher = null;

    /**
     * Instance of the database engine
     *
     * @var null
     */
    private $_engine = null;

    /**
     * Retuns a Singleton instance of the sanitizer
     *
     * @return null|Sanitizer
     */
    public static function getInstance()
    {
        if (null == self::$_sanitizer) {
            self::$_sanitizer = new Sanitizer();
        }

        return self::$_sanitizer;
    }

    /**
     * Returns the version of sanitiser
     *
     * @return string
     */
    public static function getVersion()
    {
        return self::VERSION;;
    }

    protected function configure()
    {
        $this
            ->setName('sanitize')
            ->setDescription('Sanitises a database')
            ->addArgument(
                'engine',
                InputArgument::OPTIONAL,
                'Database Engine',
                Config::INPUT_ENGINE
            )
            ->addOption(
                'host',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Database Host',
                Config::INPUT_HOST
            )
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_REQUIRED,
                'Database Password',
                Config::INPUT_PASSWORD_DEFAULT
            )
            ->addOption(
                'username',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Database User',
                Config::INPUT_USER
            )
            ->addOption(
                'database',
                'db',
                InputOption::VALUE_OPTIONAL,
                'Database',
                Config::INPUT_DATABASE
            )
            ->addOption(
                'configuration',
                null,
                InputOption::VALUE_OPTIONAL,
                'Database JSON Config File',
                Config::INPUT_CONFIGURATION_FILE
            )
            ->addOption(
                'mode',
                null,
                InputOption::VALUE_OPTIONAL,
                'Sanitisation Mode ('.Config::SANITIZATION_MODE_FULL.'|'.Config::SANITIZATION_MODE_QUICK.')',
                Config::SANITIZATION_MODE_FULL
            )
            ->addOption(
                'memory',
                null,
                InputOption::VALUE_OPTIONAL,
                'Memory - PHP format',
                self::DEFAULT_MEMORY_NOT_SET
            )
            ->addOption(
                'export',
                null,
                InputOption::VALUE_OPTIONAL,
                'Export SQL file',
                null
            )
            ->addOption(
                'import',
                null,
                InputOption::VALUE_OPTIONAL,
                'Import SQL file',
                null
            );
    }

    public function getConfig()
    {
        if (null == $this->config) {

            try
            {
                $this->config = new SanitizerConfig($this->_input->getOption('configuration'));

                if (true == $this->config->getIsInDeveloperMode()) {
                    error_reporting(E_ALL);
                    ini_set('display_errors', 1);
                }

                $this->config->setDatabaseOverride(
                    array(
                        array('Host', $this->_input->getOption('host')),
                        array('Password', $this->_input->getOption('password')),
                        array('Username', $this->_input->getOption('username')),
                        array('Database', $this->_input->getOption('database')),
                        array('Config', $this->_input->getOption('configuration')),
                        array('Engine', $this->_input->getArgument('engine')),
                        array('Mode', $this->_input->getOption('mode'))
                    )
                );

                if (true == $this->_input->hasOption('import') && true == $this->_input->hasOption('export')) {
                    $this->config->setAdditionalOverrides(
                        array(
                            'pre_conditions' => array(
                                'import_database' => array(
                                    'source' => $this->_input->getOption('import')
                                )
                            ),
                            'post_conditions' => array(
                                'export_database' => array(
                                    'destination' => $this->_input->getOption('export')
                                )
                            ),
                        )
                    );
                }
            } catch (SanitizerException $exception) {
                $this->printLn($exception->getMessage(), 'fatal_error');
                exit(-1);
            }
        }

        return $this->config;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_input   = $input;
        $this->_output  = $output;
        $configMemory   = $this->getConfig()->getGeneralConfig()->getMemory();
        $this->setMemoryUsage($input->getOption('memory'), $configMemory, self::DEFAULT_MEMORY);
        $this->loadOutputStyles();
        $this->_loadObservers();
        $this->getLog();
        $this->outputIntro();
        $this->renderOverviewTable();
        $this->loadDatabaseEngine();
        $this->sanitize();
    }

    private function _loadObservers() 
    {
        $dispatcher = $this->getEventDispatcher();
        $observers = array(new PostConditions(), new PreConditions());

        foreach ($observers as $observer) {
            $observer->registerEvents($dispatcher);
        }
    }

    /**
     * This method returns the terminal printer instance
     *
     * @return $this
     */
    public function getTerminalPrinter() 
    {
        return $this;
    }

    /**
     * This method sets the max memory for this PHP application
     *
     * @param string $memory        This is the memory from the command line
     * @param string $configMemory  This is the memory limit from the config file
     * @param string $defaultMemory This is the default memory limit
     */
    private function setMemoryUsage($memory, $configMemory, $defaultMemory)
    {
        //If the memory has not been overridden in the command line options
        if (self::DEFAULT_MEMORY_NOT_SET == $memory) {
            $memory = $configMemory;
        }

        //If the memory has come back as zero then we revert to the default
        if (null == $memory || 0 == $memory) {
            $memory = $defaultMemory;
        }

        if (false == ini_set("memory_limit", $memory)) {
            ini_set("memory_limit", $defaultMemory);
        }
    }

    /**
     * This method returns an event dispatcher instance
     *
     * @return EventDispatcher
     */
    public function getEventDispatcher() 
    {
        if (null == $this->_eventDispatcher) {
            $this->_eventDispatcher = new EventDispatcher();
        }

        return $this->_eventDispatcher;
    }

    /**
     * This method dispatches an event
     *
     * @param $name
     * @param null $data
     */
    public function dispatch($name, $data=null) 
    {
        $dispatcher = $this->getEventDispatcher();
        $dispatcher->dispatch($name, new SimpleEvent($data));
    }

    /**
     * This method returns a singleton instance of the logger class.
     *
     * @return Logger
     */
    public function getLog() 
    {
        if (null == $this->_log) {
            $this->_log = new Logger('Sanitizer');
            $this->_log->pushHandler(new StreamHandler($this->getConfig()->getLogPath(), Logger::INFO));
            $this->dispatch('sanitizer.log.acquired', array('logger' => $this->_log));
        }

        return $this->_log;
    }

    /**
     * This method initislaised the database engine with the confured options.
     * <ul><li>Defaults</li>
     * <li>
     *
     * @throws Engine\EngineNotFoundException
     * @throws SanitizerException
     * @throws Table\TableException
     */
    private function loadDatabaseEngine()
    {
        $this->setEngine(
            EngineFactory::getSingleton(
                array
                (
                    'database_type' => $this->getConfig()->getDatabase()->getEngine(),
                    'database_name' => $this->getConfig()->getDatabase()->getDatabase(),
                    'server'        => $this->getConfig()->getDatabase()->getHost(),
                    'username'      => $this->getConfig()->getDatabase()->getUsername(),
                    'password'      => $this->getConfig()->getDatabase()->getPassword(),
                    'charset'       => 'utf8'
                )
            )
        );
        $this->dispatch('sanitizer.engine.loaded', array('engine' => $this->getEngine()));
    }

    /**
     * This method loads the output styles
     */
    public function loadOutputStyles()
    {
        $style = new OutputFormatterStyle('white', 'red', array('bold'));
        $this->_output->getFormatter()->setStyle('warning', $style);
        $style = new OutputFormatterStyle('white', 'blue', array('bold'));
        $this->_output->getFormatter()->setStyle('general', $style);
        $style = new OutputFormatterStyle('white', 'green', array('bold'));
        $this->_output->getFormatter()->setStyle('notice', $style);
        $style = new OutputFormatterStyle('white', 'red', array('bold', 'underscore'));
        $this->_output->getFormatter()->setStyle('fatal_error', $style);
    }

    /**
     * Outputs initial information on the app.
     */
    protected function outputIntro()
    {
        if (true == $this->getConfig()->getIsInDeveloperMode()
            || $this->getConfig()->getIsInDeveloperMode() == OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $this->printLn("App is in developer mode, therefore all output will be shown!", 'warning');
            $this->printLn("Verbosity ".$this->_output->getVerbosity(), 'warning');
        }

        $this->printLn("Sanitisation mode '{$this->getConfig()->getDatabase()->getSanitizationMode()}'", 'normal');
    }

    /**
     * This method renders the DB config data as a table allowing the user to confirm the data is accurate and they're
     * happy to continue.
     */
    private function renderOverviewTable()
    {
        if (true == $this->canDisplayMessage(OutputInterface::VERBOSITY_NORMAL)) {
            $tableData = $this->getConfig()->getCriticalOverviewArray($this->_output->getVerbosity());
            $this->_logTableData($tableData);
            $table = new Table($this->_output);
            $table->setHeaders(array('Setting', 'Value'))->setRows($tableData);
            $table->render();
            $this->askPermissionToContinue();
        }
    }

    /**
     * Asks user for permission to continue.
     * Exits if no permission given
     */
    protected function askPermissionToContinue()
    {
        $helper     = $this->getHelper('question');
        $question   = new ConfirmationQuestion('Are you happy to continue? [yes|no]', false);

        if (false == $helper->ask($this->_input, $this->_output, $question)) {
            $this->printLn("Exiting due to user", "log");
            exit(-1);
        }
    }

    /**
     * This method prints a line to the display.
     *
     * @param $message
     * @param null    $type
     */
    public function printLn($message, $type=null)
    {
        $this->_printCache[] = array('message' => $message, 'type' => $type);

        if (null != $this->_log) {
            $this->_log->addInfo($message, array('type' => $type));
        }

        if (false == $this->getSatitisationState()) {
            $this->purgePrintCache();
        }

        if ('fatal_error' == $type) {
            $this->purgePrintCache();
        }
    }

    /**
     * This method prints the print cache, once done the print cache is purged.
     */
    protected function purgePrintCache()
    {
        foreach ($this->_printCache as $item) {
            $message    = $item['message'];
            $type       = $item['type'];

            switch($type) {
            case null :
                $this->_output->writeLn($message);
                break;
            case 'general' :
                if (true == $this->canDisplayMessage(OutputInterface::VERBOSITY_VERY_VERBOSE)) {
                    $this->_output->writeLn($this->formatMessage($type, $message));
                }
                break;
            case 'warning' :
                if (true == $this->canDisplayMessage(OutputInterface::VERBOSITY_VERBOSE)) {
                    $this->_output->writeLn($this->formatMessage($type, $message));
                }
                break;
            case 'notice' :
                if (true == $this->canDisplayMessage(OutputInterface::VERBOSITY_VERY_VERBOSE)) {
                    $this->_output->writeLn($this->formatMessage($type, $message));
                }
                break;
            case 'normal' :
                if (true == $this->canDisplayMessage(OutputInterface::VERBOSITY_NORMAL)) {
                    $this->_output->writeLn($message);
                }
                break;
            case 'fatal_error' :
                $this->_output->writeLn($this->formatMessage($type, $message), 'warning');
                exit(1);
                break;
            }
        }
        $this->_printCache = array();
    }

    /**
     * This function returns true if the verbosity level equal to the level provided.
     * If this is in developer mode than it will always return true.
     *
     * @param  $level
     * @return bool
     */
    public function canDisplayMessage($level)
    {
        //Default to verbose
        if (true == $this->getConfig()->getIsInDeveloperMode()) {
            return true;
        }

        return ($this->_output->getVerbosity() >= $level);
    }

    /**
     * Method which adds type tags to the output if set.
     * @param $type
     * @param $message
     * @return string
     */
    protected function formatMessage($type, $message)
    {
        return $message = (null == $type) ? $message : "<{$type}>{$message}</{$type}>";;
    }

    /**
     * Returns the app mode. There are two options.
     * Sanitize, Verify
     *
     * @return mixed
     */
    public function getMode()
    {
        return 'sanitize';
    }

    /**
     * This method sets the internal flag which states that
     * sanitisation is running
     */
    public function setSatitisationRunning()
    {
        $this->_satitisationRunning = true;
    }

    /**
     * This method sets the internal flag which states that
     * sanitisation is NOT running.
     *
     * This method also purges the print cache.
     */
    public function setSatitisationNotRunning()
    {
        $this->_satitisationRunning = false;
        $this->purgePrintCache();
    }

    /**
     * This method returns true if sanitisation is running.
     *
     * @return bool
     */
    public function getSatitisationState()
    {
        return $this->_satitisationRunning;
    }

    /**
     * Starts the progress bar and sets its width to $width
     *
     * @param  $count is the count so far.
     * @param  $width is the width of the bar.
     * @return $this
     */
    public function startProgressBar($count, $width=100)
    {
        $this->_progressBar = new ProgressBar($this->_output, $count);
        $this->_progressBar->setBarWidth($width);

        return $this;
    }

    /**
     * Advances the progress bar
     *
     * @return $this
     */
    public function advanceProgressBar()
    {
        if (null != $this->_progressBar) {
            $this->_progressBar->advance();
        }

        return $this;
    }

    /**
     * Finishes the progress bar
     *
     * @return $this
     */
    public function advanceProgressFinish()
    {
        if (null != $this->_progressBar) {
            $this->_progressBar->finish();
        }

        $this->printLn("\n");
        return $this;
    }

    /**
     * Method which logs the table data.
     *
     * @param $tableData
     *
     * @return $this
     */
    private function _logTableData($tableData)
    {
        foreach ($tableData as $enteries) {
            $name   = $enteries[0];
            $value  = $enteries[1];
            $this->printLn("Sanitize settings: {$name}:{$value}", 'log');
        }

        return $this;
    }

    public function getEngine() 
    {
        if (null == $this->_engine) {
            throw new TableException("Someone has moved the Engine, I can't find it!");
        }

        return $this->_engine;
    }

    public function setEngine(EngineInterface $engine) 
    {
        $this->_engine = $engine;
    }

    private function _checkSanitizerHasTablesToSanitize($collection) 
    {
        if (0 == $collection->getAddedTableCount()) {
            $message = "No tables from config found, nothing to process";
            $this->dispatch('sanitizer.sanitize.sanitizing.fatal_error');
            $this->setSatitisationNotRunning();
            throw new SanitizerException($message);
        }
    }

    private function _checkSomeTablesAreBeingSkipped($collection) 
    {
        if (true == $collection->getSomeTablesAreBeingSkipped()) {
            $this->printLn("Some tables are being skipped", 'notice');
        }
    }

    private function _skippingSanitization() 
    {
        $message = $this->getMode().' mode selected, exiting before sanitisation';
        $this->printLn($message, 'general');
        $this->dispatch(
            'sanitizer.sanitize.sanitizing.skipping', array(
            'sanitizer' => $this,
            'message' => $message,
            'mode' => $this->getMode(),
            )
        );
    }

    /**
     * This method iterates over the tables.
     */
    protected function sanitize()
    {
        $sanitized  = array();
        $this->dispatch('sanitizer.sanitize.before', array('sanitizer' => $this));
        $this->printLn("Sanitizing...");
        $this->setSatitisationRunning();
        $this->dispatch('sanitizer.sanitize.sanitizing', array('sanitizer' => $this));
        $collection = new TableCollection($this->getEngine());
        $tables     = $collection->getCollection($this);
        $quick      = (Config::SANITIZATION_MODE_QUICK == $this->getConfig()->getDatabase()->getSanitizationMode());
        $this->_checkSomeTablesAreBeingSkipped($collection);
        $this->_checkSanitizerHasTablesToSanitize($collection);
        $this->dispatch(
            'sanitizer.sanitize.sanitizing.before', array(
            'sanitizer'             => $this,
            'table_collection'      => $tables,
            'quick'                 => $quick,
            'mode'                  => $this->getMode()
            )
        );

        if ('sanitize' == $this->getMode()) {
            $this->startProgressBar($collection->getSize());

            foreach ($tables as $table) {
                $this->dispatch('sanitizer.sanitize.table.before', array('table' => $table));
                $table->setIsQuickSanitisation($quick);
                $rows   = $table->sanitize();
                $name   = $table->getTableName();

                if (true == $table->doCommand()) {
                    $sanitized[] = "{$table->getCommand()} applied to {$name} and effected {$rows} rows";
                } else {
                    $sanitized[] = "Sanitized {$table->getTableName()} and updated {$rows} rows";
                }

                $this->dispatch('sanitizer.sanitize.table.after', array('table' => $table));
                $this->advanceProgressBar();
            }

            $this->setSatitisationNotRunning();
            $this->advanceProgressFinish();

            foreach ($sanitized as $san) {
                $this->printLn($san, 'notice');
            }
        } else {
            $this->_skippingSanitization();
        }

        $this->setSatitisationNotRunning();
        $this->dispatch(
            'sanitizer.sanitize.sanitizing.after', array(
            'sanitizer'             => $this,
            'table_collection'      => $tables,
            'quick'                 => $quick,
            'mode'                  => $this->getMode()
            )
        );
        $this->printLn("Sanitizer finished!");
        $this->dispatch('sanitizer.sanitize.after', array('sanitizer' => $this));
    }
}