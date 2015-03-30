<?php
/*********************************************************************
 *
 * $Id: yocto_poweroutput.php 19611 2015-03-05 10:40:15Z seb $
 *
 * Implements YPowerOutput, the high-level API for PowerOutput functions
 *
 * - - - - - - - - - License information: - - - - - - - - - 
 *
 *  Copyright (C) 2011 and beyond by Yoctopuce Sarl, Switzerland.
 *
 *  Yoctopuce Sarl (hereafter Licensor) grants to you a perpetual
 *  non-exclusive license to use, modify, copy and integrate this
 *  file into your software for the sole purpose of interfacing
 *  with Yoctopuce products.
 *
 *  You may reproduce and distribute copies of this file in
 *  source or object form, as long as the sole purpose of this
 *  code is to interface with Yoctopuce products. You must retain
 *  this notice in the distributed source file.
 *
 *  You should refer to Yoctopuce General Terms and Conditions
 *  for additional information regarding your rights and
 *  obligations.
 *
 *  THE SOFTWARE AND DOCUMENTATION ARE PROVIDED 'AS IS' WITHOUT
 *  WARRANTY OF ANY KIND, EITHER EXPRESS OR IMPLIED, INCLUDING 
 *  WITHOUT LIMITATION, ANY WARRANTY OF MERCHANTABILITY, FITNESS
 *  FOR A PARTICULAR PURPOSE, TITLE AND NON-INFRINGEMENT. IN NO
 *  EVENT SHALL LICENSOR BE LIABLE FOR ANY INCIDENTAL, SPECIAL,
 *  INDIRECT OR CONSEQUENTIAL DAMAGES, LOST PROFITS OR LOST DATA,
 *  COST OF PROCUREMENT OF SUBSTITUTE GOODS, TECHNOLOGY OR 
 *  SERVICES, ANY CLAIMS BY THIRD PARTIES (INCLUDING BUT NOT 
 *  LIMITED TO ANY DEFENSE THEREOF), ANY CLAIMS FOR INDEMNITY OR
 *  CONTRIBUTION, OR OTHER SIMILAR COSTS, WHETHER ASSERTED ON THE
 *  BASIS OF CONTRACT, TORT (INCLUDING NEGLIGENCE), BREACH OF
 *  WARRANTY, OR OTHERWISE.
 *
 *********************************************************************/

//--- (YPowerOutput return codes)
//--- (end of YPowerOutput return codes)
//--- (YPowerOutput definitions)
if(!defined('Y_VOLTAGE_OFF'))                define('Y_VOLTAGE_OFF',               0);
if(!defined('Y_VOLTAGE_OUT3V3'))             define('Y_VOLTAGE_OUT3V3',            1);
if(!defined('Y_VOLTAGE_OUT5V'))              define('Y_VOLTAGE_OUT5V',             2);
if(!defined('Y_VOLTAGE_INVALID'))            define('Y_VOLTAGE_INVALID',           -1);
//--- (end of YPowerOutput definitions)

//--- (YPowerOutput declaration)
/**
 * YPowerOutput Class: External power supply control interface
 *
 * Yoctopuce application programming interface allows you to control
 * the power ouput featured on some devices such as the Yocto-Serial.
 */
class YPowerOutput extends YFunction
{
    const VOLTAGE_OFF                    = 0;
    const VOLTAGE_OUT3V3                 = 1;
    const VOLTAGE_OUT5V                  = 2;
    const VOLTAGE_INVALID                = -1;
    //--- (end of YPowerOutput declaration)

    //--- (YPowerOutput attributes)
    protected $_voltage                  = Y_VOLTAGE_INVALID;            // PowerOuputVoltage
    //--- (end of YPowerOutput attributes)

    function __construct($str_func)
    {
        //--- (YPowerOutput constructor)
        parent::__construct($str_func);
        $this->_className = 'PowerOutput';

        //--- (end of YPowerOutput constructor)
    }

    //--- (YPowerOutput implementation)

    function _parseAttr($name, $val)
    {
        switch($name) {
        case 'voltage':
            $this->_voltage = intval($val);
            return 1;
        }
        return parent::_parseAttr($name, $val);
    }

    /**
     * Returns the voltage on the power ouput featured by
     * the module.
     *
     * @return a value among Y_VOLTAGE_OFF, Y_VOLTAGE_OUT3V3 and Y_VOLTAGE_OUT5V corresponding to the
     * voltage on the power ouput featured by
     *         the module
     *
     * On failure, throws an exception or returns Y_VOLTAGE_INVALID.
     */
    public function get_voltage()
    {
        if ($this->_cacheExpiration <= YAPI::GetTickCount()) {
            if ($this->load(YAPI::$defaultCacheValidity) != YAPI_SUCCESS) {
                return Y_VOLTAGE_INVALID;
            }
        }
        return $this->_voltage;
    }

    /**
     * Changes the voltage on the power output provided by the
     * module. Remember to call the saveToFlash() method of the module if the
     * modification must be kept.
     *
     * @param newval : a value among Y_VOLTAGE_OFF, Y_VOLTAGE_OUT3V3 and Y_VOLTAGE_OUT5V corresponding to
     * the voltage on the power output provided by the
     *         module
     *
     * @return YAPI_SUCCESS if the call succeeds.
     *
     * On failure, throws an exception or returns a negative error code.
     */
    public function set_voltage($newval)
    {
        $rest_val = strval($newval);
        return $this->_setAttr("voltage",$rest_val);
    }

    /**
     * Retrieves a dual power  ouput control for a given identifier.
     * The identifier can be specified using several formats:
     * <ul>
     * <li>FunctionLogicalName</li>
     * <li>ModuleSerialNumber.FunctionIdentifier</li>
     * <li>ModuleSerialNumber.FunctionLogicalName</li>
     * <li>ModuleLogicalName.FunctionIdentifier</li>
     * <li>ModuleLogicalName.FunctionLogicalName</li>
     * </ul>
     *
     * This function does not require that the power ouput control is online at the time
     * it is invoked. The returned object is nevertheless valid.
     * Use the method YPowerOutput.isOnline() to test if the power ouput control is
     * indeed online at a given time. In case of ambiguity when looking for
     * a dual power  ouput control by logical name, no error is notified: the first instance
     * found is returned. The search is performed first by hardware name,
     * then by logical name.
     *
     * @param func : a string that uniquely characterizes the power ouput control
     *
     * @return a YPowerOutput object allowing you to drive the power ouput control.
     */
    public static function FindPowerOutput($func)
    {
        // $obj                    is a YPowerOutput;
        $obj = YFunction::_FindFromCache('PowerOutput', $func);
        if ($obj == null) {
            $obj = new YPowerOutput($func);
            YFunction::_AddToCache('PowerOutput', $func, $obj);
        }
        return $obj;
    }

    public function voltage()
    { return $this->get_voltage(); }

    public function setVoltage($newval)
    { return $this->set_voltage($newval); }

    /**
     * Continues the enumeration of dual power ouput controls started using yFirstPowerOutput().
     *
     * @return a pointer to a YPowerOutput object, corresponding to
     *         a dual power  ouput control currently online, or a null pointer
     *         if there are no more dual power ouput controls to enumerate.
     */
    public function nextPowerOutput()
    {   $resolve = YAPI::resolveFunction($this->_className, $this->_func);
        if($resolve->errorType != YAPI_SUCCESS) return null;
        $next_hwid = YAPI::getNextHardwareId($this->_className, $resolve->result);
        if($next_hwid == null) return null;
        return yFindPowerOutput($next_hwid);
    }

    /**
     * Starts the enumeration of dual power ouput controls currently accessible.
     * Use the method YPowerOutput.nextPowerOutput() to iterate on
     * next dual power ouput controls.
     *
     * @return a pointer to a YPowerOutput object, corresponding to
     *         the first dual power ouput control currently online, or a null pointer
     *         if there are none.
     */
    public static function FirstPowerOutput()
    {   $next_hwid = YAPI::getFirstHardwareId('PowerOutput');
        if($next_hwid == null) return null;
        return self::FindPowerOutput($next_hwid);
    }

    //--- (end of YPowerOutput implementation)

};

//--- (PowerOutput functions)

/**
 * Retrieves a dual power  ouput control for a given identifier.
 * The identifier can be specified using several formats:
 * <ul>
 * <li>FunctionLogicalName</li>
 * <li>ModuleSerialNumber.FunctionIdentifier</li>
 * <li>ModuleSerialNumber.FunctionLogicalName</li>
 * <li>ModuleLogicalName.FunctionIdentifier</li>
 * <li>ModuleLogicalName.FunctionLogicalName</li>
 * </ul>
 *
 * This function does not require that the power ouput control is online at the time
 * it is invoked. The returned object is nevertheless valid.
 * Use the method YPowerOutput.isOnline() to test if the power ouput control is
 * indeed online at a given time. In case of ambiguity when looking for
 * a dual power  ouput control by logical name, no error is notified: the first instance
 * found is returned. The search is performed first by hardware name,
 * then by logical name.
 *
 * @param func : a string that uniquely characterizes the power ouput control
 *
 * @return a YPowerOutput object allowing you to drive the power ouput control.
 */
function yFindPowerOutput($func)
{
    return YPowerOutput::FindPowerOutput($func);
}

/**
 * Starts the enumeration of dual power ouput controls currently accessible.
 * Use the method YPowerOutput.nextPowerOutput() to iterate on
 * next dual power ouput controls.
 *
 * @return a pointer to a YPowerOutput object, corresponding to
 *         the first dual power ouput control currently online, or a null pointer
 *         if there are none.
 */
function yFirstPowerOutput()
{
    return YPowerOutput::FirstPowerOutput();
}

//--- (end of PowerOutput functions)
?>