<?php

namespace League\BooBoo;

use League\BooBoo\Exception\NoFormattersRegisteredException;
use League\BooBoo\Formatter;
use League\BooBoo\Handler;
use Mockery;
use PHPUnit\Framework\TestCase;

function error_get_last()
{
    return [
        'type' => BooBooExt::$LAST_ERROR,
        'message' => 'error in file',
        'file' => 'test.php',
        'line' => 8,
    ];
}

class BooBooExt extends BooBoo {

    static public $LAST_ERROR = E_ERROR;

    public function getSilence() {
        return $this->silenceErrors;
    }

    public function register() {
        parent::register();
        $this->registered = true;
    }

    public function deregister() {
        parent::deregister();
        $this->registered = false;
    }

    protected function terminate()
    {
        return;
    }

}

class RunnerTest extends TestCase {

    /**
     * @var BooBoo
     */
    protected $runner;

    /**
     * @var Mockery\MockInterface
     */
    protected $formatter;
    protected $handler;

    protected function setUp() :void {
        ini_set('display_errors', true);
        $this->runner = new BooBoo([]);

        $this->handler = Mockery::mock('League\BooBoo\Handler\HandlerInterface');
    }

    public function testHandlerMethods() {
        $runner = new BooBoo([]);

        $this->assertEmpty($runner->getHandlers());

        $runner->pushHandler(Mockery::mock('League\BooBoo\Handler\HandlerInterface'));
        $runner->pushHandler(Mockery::mock('League\BooBoo\Handler\HandlerInterface'));

        $this->assertEquals(2, count($runner->getHandlers()));
        $this->assertInstanceOf('League\BooBoo\Handler\HandlerInterface', $runner->popHandler());
        $this->assertEquals(1, count($runner->getHandlers()));

        $runner->clearHandlers();
        $this->assertEmpty($runner->getHandlers());
    }


    public function testErrorsSilencedWhenSilenceTrue() {
        $runner = new BooBoo();
        $runner->silenceAllErrors(true);

        // Now we fake an error
        $result = $runner->errorHandler(E_WARNING, 'warning', 'index.php', 11);
        $this->assertTrue($result);

    }

    public function testThrowErrorsAsExceptions() {
        error_reporting(E_ALL);
        $this->expectException(\ErrorException::class);
        $this->runner->treatErrorsAsExceptions(true);
        $this->runner->errorHandler(E_WARNING, 'test', 'test.php', 11);
    }

    public function testErrorReportingOffSilencesErrors() {
        error_reporting(0);
        $result = $this->runner->errorHandler(E_WARNING, 'error', 'index.php', 11);
        $this->assertTrue($result);
        error_reporting(E_ALL);
    }


    public function testErrorReportingOffStillKillsFatalErrors() {
        error_reporting(0);
        $runner = new BooBooExt([]);
        $result = $runner->errorHandler(E_ERROR, 'error', 'index.php', 11);
        $this->assertTrue($result);
        error_reporting(E_ALL);
    }

    public function testErrorsSilencedWhenErrorReportingOff() {
        $er = ini_get('display_errors');
        ini_set('display_errors', 0);

        $runner = new BooBooExt([]);
        ini_set('display_errors', $er);

        $this->assertTrue($runner->getSilence());
    }

    public function testRegisterAndDeregister() {

        $runner = new BooBooExt();

        $runner->register();
        $this->assertTrue($runner->registered);

        $runner->deregister();
        $this->assertFalse($runner->registered);
    }

    public function testHandlersAreRun()
    {
        $runner = new BooBoo([]);

        $this->assertEmpty($runner->getHandlers());

        $handler = Mockery::mock('League\BooBoo\Handler\HandlerInterface');
        $handler->shouldReceive('handle')->once()->with(Mockery::type('Exception'));

        $runner->pushHandler($handler);
        $runner->exceptionHandler(new \Exception);
    }

    protected function tearDown() : void
    {
        Mockery::close();
    }
}
