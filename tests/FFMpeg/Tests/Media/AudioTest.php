<?php

namespace FFMpeg\Tests\Media;

use FFMpeg\Media\Audio;
use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
use FFMpeg\Format\AudioInterface;

class AudioTest extends AbstractStreamableTestCase
{
    public function testFiltersReturnsAudioOptions()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $audio = new Audio(__FILE__, $driver, $ffprobe);
        $this->assertInstanceOf('FFMpeg\Options\Audio\AudioOptions', $audio->options());
    }

    public function testAddFiltersAddsAFilter()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $options = $this->getMockBuilder('FFMpeg\Options\OptionsCollection')
            ->disableOriginalConstructor()
            ->getMock();

        $audio = new Audio(__FILE__, $driver, $ffprobe);
        $audio->setOptionsCollection($options);

        $option = $this->getMock('FFMpeg\Options\Audio\AudioOptionInterface');

        $options->expects($this->once())
            ->method('add')
            ->with($option);

        $audio->addOption($option);
    }

    public function testAddAVideoFilterThrowsException()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $options = $this->getMockBuilder('FFMpeg\Options\OptionsCollection')
            ->disableOriginalConstructor()
            ->getMock();

        $audio = new Audio(__FILE__, $driver, $ffprobe);
        $audio->setOptionsCollection($options);

        $option = $this->getMock('FFMpeg\Options\Video\VideoOptionInterface');

        $options->expects($this->never())
            ->method('add');

        $this->setExpectedException('FFMpeg\Exception\InvalidArgumentException');
        $audio->addOption($option);
    }

    public function testSaveWithFailure()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();
        $outputPathfile = '/target/file';

        $format = $this->getMock('FFMpeg\Format\AudioInterface');
        $format->expects($this->any())
            ->method('getExtraParams')
            ->will($this->returnValue(array()));

        $configuration = $this->getMock('Alchemy\BinaryDriver\ConfigurationInterface');

        $driver->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $failure = new ExecutionFailureException('failed to encode');
        $driver->expects($this->once())
            ->method('command')
            ->will($this->throwException($failure));

        $audio = new Audio(__FILE__, $driver, $ffprobe);
        $this->setExpectedException('FFMpeg\Exception\RuntimeException');
        $audio->save($format, $outputPathfile);
    }

    public function testSaveAppliesFilters()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();
        $outputPathfile = '/target/file';
        $format = $this->getMock('FFMpeg\Format\AudioInterface');
        $format->expects($this->any())
            ->method('getExtraParams')
            ->will($this->returnValue(array()));

        $configuration = $this->getMock('Alchemy\BinaryDriver\ConfigurationInterface');

        $driver->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $audio = new Audio(__FILE__, $driver, $ffprobe);

        $option = $this->getMock('FFMpeg\Options\Audio\AudioOptionInterface');
        $option->expects($this->once())
            ->method('apply')
            ->with($audio, $format)
            ->will($this->returnValue(array('extra-filter-command')));

        $capturedCommands = array();

        $driver->expects($this->once())
            ->method('command')
            ->with($this->isType('array'), false, $this->anything())
            ->will($this->returnCallback(function ($commands, $errors, $listeners) use (&$capturedCommands) {
                $capturedCommands[] = $commands;
            }));

        $audio->addOption($option);
        $audio->save($format, $outputPathfile);

        foreach ($capturedCommands as $commands) {
            $this->assertEquals('-y', $commands[0]);
            $this->assertEquals('-i', $commands[1]);
            $this->assertEquals(__FILE__, $commands[2]);
            $this->assertEquals('extra-filter-command', $commands[3]);
        }
    }

    /**
     * @dataProvider provideSaveData
     */
    public function testSaveShouldSave($threads, $expectedCommands, $expectedListeners, $format)
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $configuration = $this->getMock('Alchemy\BinaryDriver\ConfigurationInterface');

        $driver->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $configuration->expects($this->once())
            ->method('has')
            ->with($this->equalTo('ffmpeg.threads'))
            ->will($this->returnValue($threads));

        if ($threads) {
            $configuration->expects($this->once())
                ->method('get')
                ->with($this->equalTo('ffmpeg.threads'))
                ->will($this->returnValue(24));
        } else {
            $configuration->expects($this->never())
                ->method('get');
        }

        $capturedCommand = $capturedListeners = null;

        $driver->expects($this->once())
            ->method('command')
            ->with($this->isType('array'), false, $this->anything())
            ->will($this->returnCallback(function ($commands, $errors, $listeners) use (&$capturedCommand, &$capturedListeners) {
                $capturedCommand = $commands;
                $capturedListeners = $listeners;
            }));

        $outputPathfile = '/target/file';

        $audio = new Audio(__FILE__, $driver, $ffprobe);
        $audio->save($format, $outputPathfile);

        $this->assertEquals($expectedCommands, $capturedCommand);
        $this->assertEquals($expectedListeners, $capturedListeners);
    }

    public function provideSaveData()
    {
        $format = $this->getMock('FFMpeg\Format\AudioInterface');
        $format->expects($this->any())
            ->method('getExtraParams')
            ->will($this->returnValue(array()));
        $format->expects($this->any())
            ->method('getAudioKiloBitrate')
            ->will($this->returnValue(663));
        $format->expects($this->any())
            ->method('getAudioChannels')
            ->will($this->returnValue(5));

        $audioFormat = $this->getMock('FFMpeg\Format\AudioInterface');
        $audioFormat->expects($this->any())
            ->method('getExtraParams')
            ->will($this->returnValue(array()));
        $audioFormat->expects($this->any())
            ->method('getAudioKiloBitrate')
            ->will($this->returnValue(664));
        $audioFormat->expects($this->any())
            ->method('getAudioChannels')
            ->will($this->returnValue(5));
        $audioFormat->expects($this->any())
            ->method('getAudioCodec')
            ->will($this->returnValue('patati-patata-audio'));

        $formatExtra = $this->getMock('FFMpeg\Format\AudioInterface');
        $formatExtra->expects($this->any())
            ->method('getExtraParams')
            ->will($this->returnValue(array('extra', 'param')));
        $formatExtra->expects($this->any())
            ->method('getAudioKiloBitrate')
            ->will($this->returnValue(665));
        $formatExtra->expects($this->any())
            ->method('getAudioChannels')
            ->will($this->returnValue(5));

        $listeners = array($this->getMock('Alchemy\BinaryDriver\Listeners\ListenerInterface'));

        $progressableFormat = $this->getMockBuilder('FFMpeg\Tests\Media\AudioProg')
            ->disableOriginalConstructor()->getMock();
        $progressableFormat->expects($this->any())
            ->method('getExtraParams')
            ->will($this->returnValue(array()));
        $progressableFormat->expects($this->any())
            ->method('createProgressListener')
            ->will($this->returnValue($listeners));
        $progressableFormat->expects($this->any())
            ->method('getAudioKiloBitrate')
            ->will($this->returnValue(666));
        $progressableFormat->expects($this->any())
            ->method('getAudioChannels')
            ->will($this->returnValue(5));

        return array(
            array(false, array(
                    '-y', '-i', __FILE__,
                    '-b:a', '663k',
                    '-ac', '5',
                    '/target/file',
                ), null, $format),
            array(false, array(
                    '-y', '-i', __FILE__,
                    '-acodec', 'patati-patata-audio',
                    '-b:a', '664k',
                    '-ac', '5',
                    '/target/file',
                ), null, $audioFormat),
            array(false, array(
                    '-y', '-i', __FILE__,
                    'extra', 'param',
                    '-b:a', '665k',
                    '-ac', '5',
                    '/target/file',
                ), null, $formatExtra),
            array(true, array(
                    '-y', '-i', __FILE__,
                    '-threads', 24,
                    '-b:a', '663k',
                    '-ac', '5',
                    '/target/file',
                ), null, $format),
            array(true, array(
                    '-y', '-i', __FILE__,
                    'extra', 'param',
                    '-threads', 24,
                    '-b:a', '665k',
                    '-ac', '5',
                    '/target/file',
                ), null, $formatExtra),
            array(false, array(
                    '-y', '-i', __FILE__,
                    '-b:a', '666k',
                    '-ac', '5',
                    '/target/file',
                ), $listeners, $progressableFormat),
            array(true, array(
                    '-y', '-i', __FILE__,
                    '-threads', 24,
                    '-b:a', '666k',
                    '-ac', '5',
                    '/target/file',
                ), $listeners, $progressableFormat),
        );
    }

    public function testSaveShouldNotStoreCodecFiltersInTheMedia()
    {
        $driver = $this->getFFMpegDriverMock();
        $ffprobe = $this->getFFProbeMock();

        $configuration = $this->getMock('Alchemy\BinaryDriver\ConfigurationInterface');

        $driver->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $configuration->expects($this->any())
            ->method('has')
            ->with($this->equalTo('ffmpeg.threads'))
            ->will($this->returnValue(true));

        $configuration->expects($this->any())
            ->method('get')
            ->with($this->equalTo('ffmpeg.threads'))
            ->will($this->returnValue(24));

        $capturedCommands = array();

        $driver->expects($this->exactly(2))
            ->method('command')
            ->with($this->isType('array'), false, $this->anything())
            ->will($this->returnCallback(function ($commands, $errors, $listeners) use (&$capturedCommands, &$capturedListeners) {
                $capturedCommands[] = $commands;
            }));

        $outputPathfile = '/target/file';

        $format = $this->getMock('FFMpeg\Format\AudioInterface');
        $format->expects($this->any())
            ->method('getExtraParams')
            ->will($this->returnValue(array('param')));

        $audio = new Audio(__FILE__, $driver, $ffprobe);
        $audio->save($format, $outputPathfile);
        $audio->save($format, $outputPathfile);

        $expected = array(
            '-y', '-i', __FILE__, 'param', '-threads', 24, '/target/file',
        );

        foreach ($capturedCommands as $capturedCommand) {
            $this->assertEquals($expected, $capturedCommand);
        }
    }

    public function getClassName()
    {
        return 'FFMpeg\Media\Audio';
    }
}
