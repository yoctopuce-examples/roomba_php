<?php

//include('../../Sources/yocto_api.php');
//include('../../Sources/yocto_serialport.php');
require_once('yoctolib/yocto_api.php');
require_once('yoctolib/yocto_serialport.php');

/**
 * Created by PhpStorm.
 * User: seb
 * Date: 05.03.2015
 * Time: 09:58
 */
class Roomba
{

    private $wheel_drop_left;

    private function decodeUnsigned($data)
    {
        $value = 0;
        $count = count($data);
        for ($i = 0; $i < $count; $i++) {
            $value <<= 8;
            $value += $data[$i] & 0xff;
        }
        return $value;
    }

    private function decodeSigned($data)
    {
        $short = $this->decodeUnsigned($data);
        if($short >= 32768) {
            $short -= 65536;
        }
        return $short;
    }


    private function decodeBool($data)
    {
        $byte = (int)$data[0];
        return ($byte & 1) != 0;
    }

    private function decodeBumpsAndWheelDrops($data)
    {
        $raw = $data[0];
        return array("wheel_drop_left" => (($raw & 8) != 0),
            "wheel_drop_right" => (($raw & 4) != 0),
            "bump_left" => (($raw & 2) != 0),
            "bump_right" => (($raw & 1) != 0));
    }

    private function decodeWheelOvercurrents($data)
    {
        $byte = (int)$data[0];
        return array(
            "leftWheel" => (($byte & 16) != 0),
            "rightWheel" => (($byte & 8) != 0),
            "mainbrush" => (($byte & 4) != 0),
            "sidebrush" => (($byte & 1) != 0)
        );
    }

    private function decodeChargingSource($data)
    {
        $byte = (int)$data[0];
        return array(
            "dock" => (($byte & 2) != 0),
            "internal" => (($byte & 1) != 0)
        );
    }

    private function decodeButton($data)
    {
        $byte = (int)$data[0];
        return array(
            "clock" => (($byte & 128) != 0),
            "schedule" => (($byte & 64) != 0),
            "day" => (($byte & 32) != 0),
            "hour" => (($byte & 16) != 0),
            "minute" => (($byte & 8) != 0),
            "Dock" => (($byte & 4) != 0),
            "spot" => (($byte & 2) != 0),
            "clean" => (($byte & 1) != 0)
        );
    }


    private function decodegroup7_26($all_data)
    {
        $ofs = 0;
        $res = array();
        for ($id = 7; $id < 26; $id++) {
            $sensor_info = $this->decode_map[$id];
            $size = $sensor_info['size'];
            $ret_bytes = array_slice($all_data, $ofs, $size);
            $res[$sensor_info['name']] = call_user_func($sensor_info['decoder'], $ret_bytes);
            $ofs += $size;
        }
        return $res;
    }


    private function decodegroup($all_data, $from, $to)
    {
        $ofs = 0;
        $res = array();
        //print("decode group $from ->$to\n");
        for ($id = $from; $id < $to; $id++) {
            $sensor_info = $this->decode_map[$id];
            $name = $sensor_info['name'];
            //print("decode group $id ($name)\n");
            $size = $sensor_info['size'];
            $ret_bytes = array_slice($all_data, $ofs, $size);
            $res[$name] = call_user_func($sensor_info['decoder'], $ret_bytes);
            $ofs += $size;
        }
        return $res;
    }


    private function decodegroup7_16($all_data)
    {
        return $this->decodegroup($all_data, 7, 16);
    }


    private function decodegroup17_20($all_data)
    {
        return $this->decodegroup($all_data, 17, 20);
    }

    private function decodegroup21_26($all_data)
    {
        return $this->decodegroup($all_data, 21, 26);
    }

    private function decodegroup27_34($all_data)
    {
        return $this->decodegroup($all_data, 27, 34);
    }

    private function decodegroup35_42($all_data)
    {
        return $this->decodegroup($all_data, 35, 42);
    }

    private function decodegroup7_42($all_data)
    {
        return $this->decodegroup($all_data, 7, 42);
    }

    private function decodeIOMode($data)
    {
        $index = 0;
        if(count($data) == 1) {
            $index = $data[0];
        }
        return $this->modes[$index];
    }


    /**
     * Yoctopuce Yocto-Serial interface
     * @var YSerialPort
     */
    private $serial_port;
    private $decode_map;
    public $modes = array("Off", "Passive", "Safe", "Full");

    function __construct($address)
    {

        // Use explicit error handling rather than exceptions
        YAPI::DisableExceptions();

        // Setup the API to use the VirtualHub on local machine,
        if(YAPI::RegisterHub($address, $errmsg) != YAPI_SUCCESS) {
            die("Cannot contact $address");
        }

        $this->serial_port = YSerialPort::FirstSerialPort();
        if($this->serial_port == null)
            die("No module found on $address (check USB cable)");

        // setup the serial parameter with the Roomba
        // serie 600 and following communicate at 115200 bauds
        $this->serial_port->set_serialMode("115200,8N1");
        // let the serial port wait 20ms between each writes
        $this->serial_port->set_protocol("Frame:20ms");
        // clear all buffer of the serial port
        $this->serial_port->reset();

        // initalize some internal var
        $this->decode_map = array(
            0 => array('id' => 0, 'size' => 26, 'name' => 'group 7-26', 'decoder' => array($this, 'decodegroup7_26')),
            1 => array('id' => 1, 'size' => 10, 'name' => 'group 7-16', 'decoder' => array($this, 'decodegroup7_16')),
            2 => array('id' => 2, 'size' => 6, 'name' => 'group 17-20', 'decoder' => array($this, 'decodegroup17_20')),
            3 => array('id' => 3, 'size' => 10, 'name' => 'group 21-26', 'decoder' => array($this, 'decodegroup21_26')),
            4 => array('id' => 4, 'size' => 14, 'name' => 'group 27-34', 'decoder' => array($this, 'decodegroup27_34')),
            5 => array('id' => 5, 'size' => 12, 'name' => 'group 35-42', 'decoder' => array($this, 'decodegroup35_42')),
            6 => array('id' => 6, 'size' => 52, 'name' => 'group 7-42', 'decoder' => array($this, 'decodegroup7_42')),
            7 => array('id' => 7, 'size' => 1, 'name' => 'Bumps and Wheel Drops', 'decoder' => array($this, 'decodeBumpsAndWheelDrops')),
            8 => array('id' => 8, 'size' => 1, 'name' => 'Wall', 'decoder' => array($this, 'decodeBool')),
            9 => array('id' => 9, 'size' => 1, 'name' => 'Cliff Left', 'decoder' => array($this, 'decodeBool')),
            10 => array('id' => 10, 'size' => 1, 'name' => 'Cliff Front Left', 'decoder' => array($this, 'decodeBool')),
            11 => array('id' => 11, 'size' => 1, 'name' => 'Cliff Front Right', 'decoder' => array($this, 'decodeBool')),
            12 => array('id' => 12, 'size' => 1, 'name' => 'Cliff Right', 'decoder' => array($this, 'decodeBool')),
            13 => array('id' => 13, 'size' => 1, 'name' => 'Virtual Wall', 'decoder' => array($this, 'decodeBool')),
            14 => array('id' => 14, 'size' => 1, 'name' => 'Wheel Overcurrents', 'decoder' => array($this, 'decodeWheelOvercurrents')),
            15 => array('id' => 15, 'size' => 1, 'name' => 'Dirt Detect', 'decoder' => array($this, 'decodeUnsigned')),
            16 => array('id' => 16, 'size' => 1, 'name' => 'unused', 'decoder' => array($this, 'decodeUnsigned')),
            17 => array('id' => 17, 'size' => 1, 'name' => 'Infrared Character Omni', 'decoder' => array($this, 'decodeUnsigned')),
            18 => array('id' => 18, 'size' => 1, 'name' => 'Buttons', 'decoder' => array($this, 'decodeButton')),
            19 => array('id' => 19, 'size' => 2, 'name' => 'Distance', 'decoder' => array($this, 'decodeSigned')),
            20 => array('id' => 20, 'size' => 2, 'name' => 'Angle', 'decoder' => array($this, 'decodeSigned')),
            21 => array('id' => 21, 'size' => 1, 'name' => 'Charging State', 'decoder' => array($this, 'decodeChargingState')),
            22 => array('id' => 22, 'size' => 2, 'name' => 'Voltage', 'decoder' => array($this, 'decodeUnsigned')),
            23 => array('id' => 23, 'size' => 2, 'name' => 'Current', 'decoder' => array($this, 'decodeUnsigned')),
            24 => array('id' => 24, 'size' => 1, 'name' => 'Temperature', 'decoder' => array($this, 'decodeUnsigned')),
            25 => array('id' => 25, 'size' => 2, 'name' => 'Battery Charge', 'decoder' => array($this, 'decodeUnsigned')),
            26 => array('id' => 26, 'size' => 2, 'name' => 'Battery Capacity', 'decoder' => array($this, 'decodeUnsigned')),
            27 => array('id' => 27, 'size' => 2, 'name' => 'Wall Signal', 'decoder' => array($this, 'decodeUnsigned')),
            28 => array('id' => 28, 'size' => 2, 'name' => 'Cliff Left Signal', 'decoder' => array($this, 'decodeUnsigned')),
            29 => array('id' => 29, 'size' => 2, 'name' => 'Cliff Front Left Signa', 'decoder' => array($this, 'decodeUnsigned')),
            30 => array('id' => 30, 'size' => 2, 'name' => 'Cliff Front Right Signal', 'decoder' => array($this, 'decodeUnsigned')),
            31 => array('id' => 31, 'size' => 2, 'name' => 'Cliff Right Signal', 'decoder' => array($this, 'decodeUnsigned')),
            32 => array('id' => 32, 'size' => 3, 'name' => 'Unused', 'decoder' => array($this, 'decodeUnsigned')),
            33 => array('id' => 33, 'size' => 3, 'name' => 'Unused', 'decoder' => array($this, 'decodeUnsigned')),
            34 => array('id' => 34, 'size' => 1, 'name' => 'Charging Sources Available', 'decoder' => array($this, 'decodeChargingSource')),
            35 => array('id' => 35, 'size' => 1, 'name' => 'OI Mode', 'decoder' => array($this, 'decodeIOMode')),
            36 => array('id' => 36, 'size' => 1, 'name' => 'Song Number', 'decoder' => array($this, 'decodeUnsigned')),
            37 => array('id' => 37, 'size' => 1, 'name' => 'Song Playing', 'decoder' => array($this, 'decodeUnsigned')),
            38 => array('id' => 38, 'size' => 1, 'name' => 'Number of Stream Packets', 'decoder' => array($this, 'decodeUnsigned')),
            39 => array('id' => 39, 'size' => 2, 'name' => 'Requested Velocity', 'decoder' => array($this, 'decodeSigned')),
            40 => array('id' => 40, 'size' => 2, 'name' => 'Requested Radius', 'decoder' => array($this, 'decodeSigned')),
            41 => array('id' => 41, 'size' => 2, 'name' => 'Requested Right Velocity', 'decoder' => array($this, 'decodeSigned')),
            42 => array('id' => 42, 'size' => 2, 'name' => 'Requested Left Velocity', 'decoder' => array($this, 'decodeSigned')),
            43 => array('id' => 43, 'size' => 2, 'name' => 'Right Encoder Counts', 'decoder' => array($this, 'decodeUnsigned')),
            44 => array('id' => 44, 'size' => 2, 'name' => 'Left Encoder Counts', 'decoder' => array($this, 'decodeUnsigned')),
            45 => array('id' => 45, 'size' => 1, 'name' => 'Light Bumper', 'decoder' => array($this, 'decodeUnsigned')),
            46 => array('id' => 46, 'size' => 2, 'name' => 'Light Bump Left Signal', 'decoder' => array($this, 'decodeUnsigned')),
            47 => array('id' => 47, 'size' => 2, 'name' => 'Light Bump Front Left Signal', 'decoder' => array($this, 'decodeUnsigned')),
            48 => array('id' => 48, 'size' => 2, 'name' => 'Light Bump Center Left Signal', 'decoder' => array($this, 'decodeUnsigned')),
            49 => array('id' => 49, 'size' => 2, 'name' => 'Light Bump Center Right Signal', 'decoder' => array($this, 'decodeUnsigned')),
            50 => array('id' => 50, 'size' => 2, 'name' => 'Light Bump Front Right Signal', 'decoder' => array($this, 'decodeUnsigned')),
            51 => array('id' => 51, 'size' => 2, 'name' => 'Light Bump Right Signal', 'decoder' => array($this, 'decodeUnsigned')),
            52 => array('id' => 52, 'size' => 1, 'name' => 'Infrared Character Left', 'decoder' => array($this, 'decodeUnsigned')),
            53 => array('id' => 53, 'size' => 1, 'name' => 'Infrared Character Right', 'decoder' => array($this, 'decodeUnsigned')),
            54 => array('id' => 54, 'size' => 2, 'name' => 'Left Motor Current', 'decoder' => array($this, 'decodeSigned')),
            55 => array('id' => 55, 'size' => 2, 'name' => 'Right Motor Current', 'decoder' => array($this, 'decodeSigned')),
            56 => array('id' => 56, 'size' => 2, 'name' => 'Main Brush Motor Current', 'decoder' => array($this, 'decodeSigned')),
            57 => array('id' => 57, 'size' => 2, 'name' => 'Side Brush Motor Current', 'decoder' => array($this, 'decodeSigned')),
            58 => array('id' => 58, 'size' => 1, 'name' => 'Stasis', 'decoder' => array($this, 'decodeUnsigned'))
        );


    }

    /**
     * @param array $cmd
     * @param int $nbytes
     * @return array of bytes
     */
    private function sendCmd($cmd, $nbytes = 0)
    {
        $this->serial_port->writeArray($cmd);
        if($nbytes > 0) {
            YAPI::Sleep(50);
            if(true) {
                return $this->serial_port->readArray($nbytes);
            } else {
                $res = $this->serial_port->readHex($nbytes);
                print("received $res\n");
                $len = strlen($res) / 2;
                $data = array();
                for ($i = 0; $i < $len; $i++) {
                    $data[] = dechex(substr($res, $i * 2, 2));
                }
                return $data;
            }
        } else {
            return array();
        }
    }


    public function StartOI()
    {
        $this->sendCmd(array(128));
    }

    public function Reset()
    {
        $this->sendCmd(array(7));
    }

    public function StopOI()
    {
        $this->sendCmd(array(173));
    }

    public function Safe()
    {
        $this->sendCmd(array(131));
    }

    public function Full()
    {
        $this->sendCmd(array(132));
    }

    public function SetMode($mode)
    {
        switch ($mode) {
            case "Off":
                $this->StopOI();
                break;
            case "Passive":
                $this->StartOI();
                break;
            case "Safe":
                $this->Safe();
                break;
            case "Full":
                $this->Full();
                break;
            default:
        }
    }

    public function Power()
    {
        $this->sendCmd(array(133));
    }


    public function Spot()
    {
        $this->sendCmd(array(134));
    }

    public function Clean()
    {
        $this->sendCmd(array(135));
    }

    public function Max()
    {
        $this->sendCmd(array(136));
    }

    /**
     * @param int $velocity
     * @param int $radius
     */
    public function Drive($velocity, $radius)
    {
        if($velocity > 500)
            $velocity = 500;
        if($velocity < -500)
            $velocity = -500;
        if($radius > 2000)
            $radius = 2000;
        if($radius < -2000)
            $radius = -2000;
        $cmd = array(137);
        $cmd[] = ((int)$velocity) >> 8;
        $cmd[] = ((int)$velocity) & 0xff;
        $cmd[] = ((int)$radius) >> 8;
        $cmd[] = ((int)$radius) & 0xff;
        $this->sendCmd($cmd, 0);
    }

    /**
     * @param bool $main_brush
     * @param bool $vacum
     * @param bool $side_brush
     */
    public function Motors($main_brush, $vacum, $side_brush)
    {
        $flags = 0;
        if($main_brush)
            $flags |= 4;
        if($vacum)
            $flags |= 2;
        if($side_brush)
            $flags |= 1;
        $this->sendCmd(array(138, $flags));
    }

    /**
     * @param int $power_color
     * @param int $power_intensity
     * @param bool $debris_led
     * @param bool $spot_led
     * @param bool $dock_led
     * @param bool $check_led
     */
    public function Leds($power_color, $power_intensity, $debris_led, $spot_led, $dock_led, $check_led)
    {
        $cmd = array(139);
        $flags = 0;
        if($debris_led)
            $flags += 1;
        if($spot_led)
            $flags += 2;
        if($dock_led)
            $flags += 4;
        if($check_led)
            $flags += 8;
        $cmd[] = $flags;
        $cmd[] = $power_color & 0xff;
        $cmd[] = $power_intensity & 0xff;
        $this->sendCmd($cmd);
    }

    public function Song($songno, $notes)
    {
        $cmd = array(140);
        $cmd[] = $songno;
        $len = sizeof($notes);
        if($len > 16) {
            $len = 16;
        }
        $cmd[] = $len;
        for ($i = 0; $i < $len; $i++) {
            $cmd[] = $notes[$i]['note'];
            $cmd[] = $notes[$i]['len'];
        }
        $this->sendCmd($cmd);
    }

    public function Play($songno)
    {
        $this->sendCmd(array(141, $songno));
    }


    public function LedASCII($str)
    {
        $cmd = array(164);
        $cmd[] = ord($str[0]);
        $cmd[] = ord($str[1]);
        $cmd[] = ord($str[2]);
        $cmd[] = ord($str[3]);
        $this->sendCmd($cmd);
    }

    public function dock()
    {
        $this->sendCmd(array(143));
    }

    public function Schedule($program)
    {
        $day_flag = 0;
        $schedule = array_fill(0, 16, 0);
        $schedule[0] = 167;
        foreach ($program as $day => $detail) {
            $ofs = (int)$day;
            $day_flag |= 1 << $ofs;
            $schedule[2 + $ofs * 2] = (int)$detail['hour'];
            $schedule[2 + $ofs * 2 + 1] = (int)$detail['minute'];
        }
        $schedule[1] = $day_flag & 0xff;
        return $this->sendCmd($schedule, 0);
    }

    public function SetTime($day, $hour, $minues)
    {
        $cmd = array(168, (int)$day, (int)$hour, (int)$minues);
        return $this->sendCmd($cmd, 0);
    }


    //input command

    private function Sensor($id)
    {
        $id = $id & 0xff;
        $sensor_info = $this->decode_map[$id];
        $ret_bytes = $this->sendCmd(array(142, $id), $sensor_info['size']);
        return call_user_func($sensor_info['decoder'], $ret_bytes);
    }


    private function decodeChargingState($data)
    {
        switch ($data[0]) {
            case 0:
                return "Not charging";
            case 1:
                return "Reconditioning charging";
            case 2:
                return "Full charging";
            case 3:
                return "Trickle charging";
            case 4:
                return "Waiting";
            case 5:
                return "Charging fault Condition";
            default:
                return "invalid";
        }
    }


    public function getSensorGroup0()
    {
        return $this->Sensor(0);
    }

    public function getSensorGroup1()
    {
        return $this->Sensor(1);
    }

    public function getSensorGroup2()
    {
        return $this->Sensor(2);
    }

    public function getSensorGroup3()
    {
        return $this->Sensor(3);
    }

    public function getSensorGroup4()
    {
        return $this->Sensor(4);
    }

    public function getSensorGroup5()
    {
        return $this->Sensor(5);
    }

    public function getSensorGroup6()
    {
        return $this->Sensor(6);
    }

    public function getBumpsAndWheelDrops()
    {
        return $this->Sensor(7);
    }

    public function getWall()
    {
        return $this->Sensor(8);
    }

    public function getCliffLeft()
    {
        return $this->Sensor(9);
    }

    public function getCliffFrontLeft()
    {
        return $this->Sensor(10);
    }

    public function getCliffFrontRight()
    {
        return $this->Sensor(11);
    }

    public function getCliffRight()
    {
        return $this->Sensor(12);
    }

    public function getVirtualWall()
    {
        return $this->Sensor(13);
    }

    public function getWheelOvercurent()
    {
        return $this->Sensor(14);
    }

    public function getDirtDetect()
    {
        return $this->Sensor(15);
    }

    public function getInfraredCharacterOmni()
    {
        return $this->Sensor(17);
    }

    public function getButtons()
    {
        return $this->Sensor(18);
    }

    public function getDistance()
    {
        return $this->Sensor(19);
    }

    public function getAngle()
    {
        return $this->Sensor(20);
    }


    public function getChargingState()
    {
        return $this->Sensor(21);
    }

    public function getVoltage()
    {
        return $this->Sensor(22);
    }

    public function getCurrent()
    {
        return $this->Sensor(23);
    }

    public function getTemperature()
    {
        return $this->Sensor(24);
    }

    public function getBatteryCharge()
    {
        return $this->Sensor(25);
    }

    public function getBatteryCapacity()
    {
        return $this->Sensor(26);
    }

    public function getWallSignal()
    {
        return $this->Sensor(27);
    }

    public function getCliffLeftSignal()
    {
        return $this->Sensor(28);
    }

    public function getCliffFrontLeftSignal()
    {
        return $this->Sensor(29);
    }

    public function getCliffFrontRightSignal()
    {
        return $this->Sensor(30);
    }

    public function getCliffRightSignal()
    {
        return $this->Sensor(31);
    }

    public function getChargingSourcesAvailable()
    {
        return $this->Sensor(34);
    }


    public function getOIMode()
    {
        return $this->Sensor(35);
    }

    public function getSongNumber()
    {
        return $this->Sensor(36);
    }

    public function getSongPlaying()
    {
        return $this->Sensor(37);
    }

    public function getNumberofStreamPackets()
    {
        return $this->Sensor(38);
    }

    public function getRequestedVelocity()
    {
        return $this->Sensor(39);
    }

    public function getRequestedRadius()
    {
        return $this->Sensor(40);
    }

    public function getRequestedRightVelocity()
    {
        return $this->Sensor(41);
    }

    public function getRequestedLeftVelocity()
    {
        return $this->Sensor(42);
    }

    public function getRightEncoderCounts()
    {
        return $this->Sensor(43);
    }

    public function getLeftEncoderCounts()
    {
        return $this->Sensor(44);
    }

    public function getLightBumper()
    {
        return $this->Sensor(45);
    }

    public function getLightBumpLeftSignal()
    {
        return $this->Sensor(46);
    }

    public function getLightBumpFrontLeftSignal()
    {
        return $this->Sensor(47);
    }

    public function getLightBumpCenterLeftSignal()
    {
        return $this->Sensor(48);
    }

    public function getLightBumpCenterRightSignal()
    {
        return $this->Sensor(49);
    }

    public function getLightBumpFrontRightSignal()
    {
        return $this->Sensor(50);
    }

    public function getLightBumpRightSignal()
    {
        return $this->Sensor(51);
    }

    public function getInfraredCharacterLeft()
    {
        return $this->Sensor(52);
    }

    public function getInfraredCharacterRight()
    {
        return $this->Sensor(53);
    }

    public function getLeftMotorCurrent()
    {
        return $this->Sensor(54);
    }

    public function getRightMotorCurrent()
    {
        return $this->Sensor(55);
    }

    public function getMainBrushMotorCurrent()
    {
        return $this->Sensor(56);
    }

    public function getSideBrushMotorCurrent()
    {
        return $this->Sensor(57);
    }

    public function isStasis()
    {
        return $this->Sensor(58);
    }


}