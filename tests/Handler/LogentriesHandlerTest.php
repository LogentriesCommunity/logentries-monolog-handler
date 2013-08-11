<?php 

use Mockery as m;
use Monolog\Logger;
use Logentries\Handler\LogentriesHandler;

class LogentriesHandlerTest extends \PHPUnit_Framework_TestCase
{
	private $log;
	private $socketMock;

	public function setUp()
	{
		$this->socketMock = m::mock('Logentries\Socket');

		$this->log = new Logger('TestLog');
		$this->log->pushHandler(new LogentriesHandler('testToken', Logger::DEBUG, true, $this->socketMock));
	}

	public function tearDown()
	{
		m::close();
	}

	public function testWarning()
	{
		$this->socketMock->shouldReceive('write')
						 ->once()
						 ->with('/testToken\s\[(\d){4}-(\d){2}-(\d){2}\s(\d){2}:(\d){2}:(\d){2}\]\sTestLog.WARNING:\sFoo\s\[\]\s\[\]/');

		$this->log->addWarning('Foo');
	}

}