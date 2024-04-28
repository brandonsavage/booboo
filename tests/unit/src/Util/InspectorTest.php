<?php

use League\BooBoo\Util\Inspector;
use PHPUnit\Framework\TestCase;

class InspectorTest extends TestCase
{
    /**
     * @var Inspector
     */
    protected $inspector;

    /**
     * @var ErrorException
     */
    protected $exception;

    public function setUp() : void
    {
        $this->exception = new ErrorException('test message');
        $this->inspector = new Inspector($this->exception);
    }

    public function testGetters()
    {
        $inspector = $this->inspector;
        $this->assertInstanceOf('ErrorException', $inspector->getException());
        $this->assertEquals('ErrorException', $inspector->getExceptionName());
        $this->assertFalse($inspector->hasPreviousException());
    }

    public function testGetFrames()
    {
        $frames = $this->inspector->getFrames();
        $this->assertEquals(count($this->exception->getTrace()), count($frames));
        $this->assertTrue($this->inspector->hasFrames());
    }

}
