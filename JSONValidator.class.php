<?php
/**
 * Class JSONValidator 
 *
 */
abstract class JSONValidator {
    protected $validationMapping = array("1"=> array("name"=>"checkExists", "params"=>array()),
                                        "2"=>array("name"=>"getFirstFromArray", "params"=>array()),
                                         );
    protected $rawData;
    protected $indexData = array();
    
    protected $errors = array();
    protected static $requiredValueMapping;

    public static abstract function getInstance($rawData);

    // constructor
    public final function __construct($rawData) {
        $this->rawData = $rawData;

        $this->validateMapping(self::$requiredValueMapping, $this->rawData, $this->indexData);
        //echo "dump<br/>"; var_dump($this->indexData);
        // echo "dis be our final payload mon<br/>";
        // echo "<pre>";
         //print_r($this->indexData);
        // echo "</pre>";
        // exit;
    }

    protected function getFirstFromArray($arr){
        if(is_array($arr)){
            foreach($arr as $index=>$val){
                if($val != null) return $val;
            }
            return null;
        }else{
            return $arr;
        }
    }
    protected function validateEmail($email) {
        if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return false;
    }

    protected function validateGender($gender) {
        if(is_string($gender)) {
            $first = strtolower($gender[0]);
            switch ($first) {
                case 'm':
                    return 'male';
                case 'f':
                    return 'female';
                default:
                    return false;
            }
        }
        return false;
    }

    protected function checkExists($value){
        if($value || $value === 0 || $value === '0') return $value;
        return false;
    }

    protected function validateNonEmptyArray($array) {
        if(is_array($array)) {
            if(count($array) > 0) {
                return $array;
            }
        }
        return false;
    }

    protected function validateEnrollmentDate($date){
        $date_unformatted = $date;
        $d_stt = strtotime($date_unformatted);
        if($d_stt > 0) {
            return date('Y-m-d', $d_stt);
        } else {
            return false;
        }
    }

    protected function handleError($severity, $message) {
        $throwException = false;
        switch ($severity) {
            case 'Debug':
                if(!$this->debug) break;
            case 'Exception':
                $throwException = true;
            default:
                error_log($message);
                break;
        }
        if($throwException) throw new Exception($message);
    }

    public function getData(){
        return $this->indexData;
    }

    protected function validateMapping($mapping, $data, &$indexData){
        if(array_key_exists("info", $mapping) && array_key_exists("multi_array", $mapping['info']) && $mapping['info']['multi_array'] === false){
            if(array_key_exists('info', $mapping) && array_key_exists('pre_op', $mapping['info'])) {
                if(array_key_exists($mapping['info']['pre_op'], $this->validationMapping)){
                    $function = $this->validationMapping[$mapping['info']['pre_op']]['name'];
                    $this->$function();
                } else if(strstr($mapping['info']['pre_op'], '>')) {
                    $operations = explode('>', $mapping['info']['pre_op']);
                    if(!sizeof($operations)) {
                        $message = "JSONValidator::validateMapping::pre_op::Invalid multi-operation description[".json_encode($mapping['info']['pre_op'])."]"; 
                        $this->handleError('Exception', $message);
                    }
                    $returnVal = null;
                    foreach($operations as $operation) {
                        if(array_key_exists($operation, $this->validationMapping)){
                            $function = $this->validationMapping[$operation]['name'];
                            $returnVal = $this->$function($returnVal);
                            if($returnVal === false){
                                $message = "JSONValidator::validateMapping::pre_op::$function failed!!!";
                                $this->handleError('Exception', $message);
                            }
                        } else {
                            $message = "JSONValidator::validateMapping::invalid operation!:[$operation]";
                            $this->handleError('Exception', $message);
                        }
                    }
                } else{
                    $message = "JSONValidator::validateMapping::BAD pre_op VALUE:: Trigger:[".json_encode($mapping['info']['pre_op'])."]";
                    $this->handleError('Exception', $message);
                }
            }
            if(array_key_exists("conditional", $mapping)){
                foreach($mapping['conditional'] as $condKey => $condArray) {
                    if(array_key_exists($condKey, $data)) {
                        $condVal = strtolower($data[$condKey]);
                        if(array_key_exists('cases', $condArray)) {
                            if(array_key_exists($condVal, $condArray['cases'])) {
                                $mapping['required'] = array_merge($mapping['required'], $condArray['cases'][$condVal]['required']);
                                $mapping['optional'] = array_merge($mapping['optional'], $condArray['cases'][$condVal]['optional']);
                            } else {
                                $message = "JSONValidator::validateMapping::CondVal[$condVal] NOT a case found in CondArray[".json_encode($condArray)."]";
                                $this->handleError('Exception', $message);
                            }
                        } else {
                            $message = "JSONValidator::validateMapping::Missing 'Cases' key in Conditional Block. CondArry:[".json_encode($condArray)."]";
                            $this->handleError('Exception', $message);
                        }
                    } else {
                        $message = "JSONValidator::validateMapping::condKey[$condKey] NOT found as existing key in data[".json_encode($data)."]";
                        $this->handleError('Exception', $message);
                    }
                }
            }
            if(array_key_exists("optional", $mapping)){
                foreach($mapping['optional'] as $reqField=>$validationType){
                    if(array_key_exists($reqField, $data)){
                        //echo "reqField: $reqField is callable? : " . ($validationType . "\n";
                        if(is_array($validationType)){
                            $this->validateMapping($mapping['optional'][$reqField], $data[$reqField]);
                        }else if(is_callable($validationType)){
                            $indexData[$reqField] = $validationType($data[$reqField]);
                        }else if(array_key_exists($validationType, $this->validationMapping)){
                            $function = $this->validationMapping[$validationType]['name'];
                            $valid = $this->$function($data[$reqField]);
                            if($valid !== false){
                                $indexData[$reqField] = $valid;
                            }   
                        }else if(strstr($validationType, '>')) {
                            $operations = explode('>', $validationType);
                            if(sizeof($operations)) {
                                $param = $data[$reqField];
                                $break = false;
                                foreach($operations as $operation) {
                                    if(array_key_exists($operation, $this->validationMapping)){
                                        $function = $this->validationMapping[$operation]['name'];
                                        $valid = $this->$function($param);
                                        if($valid !== false){
                                            $param = $valid;
                                        } else {
                                            $message = "JSONValidator::validateMapping::Optional:>:field:[$reqField] value:[" . $param . "] is Invalid";
                                            $this->handleError('Log', $message);
                                            $break = true;
                                            break;
                                        }
                                    } else {
                                        $message = "JSONValidator::validateMapping::Optional::invalid operation:[$operation]";
                                        $this->handleError('Log', $message);
                                        $break = true;
                                        break;
                                    }
                                }
                                if(!$break) $indexData[$reqField] = $valid;
                            } else {
                                $message = "JSONValidator::validateMapping::Optional::Invalid multi-operation description[".json_encode($validationType)."]";
                                $this->handleError('Log', $message);
                            }
                        }
                    }
                }
            }
            if(array_key_exists("required", $mapping)){
                foreach($mapping['required'] as $reqField => $validationType) {
                    if(!array_key_exists($reqField, $data)){
                        $message = "JSONValidator::validateMapping::Required::reqField:[$reqField] is missing";
                        $this->handleError('Exception', $message);
                    }else{
                        if(is_array($validationType)){
                            $this->validateMapping($mapping['required'][$reqField], $data[$reqField], $indexData[$reqField]);
                        }else if(is_callable($validationType)){
                            $indexData[$reqField] = $validationType($data[$reqField]);
                        }else if(array_key_exists($validationType, $this->validationMapping)){
                            $function = $this->validationMapping[$validationType]['name'];
                            $valid = $this->$function($data[$reqField]);
                            if($valid === false){
                                $message = "JSONValidator::validateMapping::Required:field:[$reqField] value:[" . $data[$reqField] . "] is Invalid";
                                $this->handleError('Exception', $message);
                            }
                            $indexData[$reqField] = $valid;
                        } else if(strstr($validationType, '>')) {
                            $operations = explode('>', $validationType);
                            if(!sizeof($operations)) {
                                $message = "JSONValidator::validateMapping::Required::Invalid multi-operation description[".json_encode($validationType)."]";
                                $this->handleError('Exception', $message);
                            }
                            $param = $data[$reqField];
                            foreach($operations as $operation) {
                                if(array_key_exists($operation, $this->validationMapping)){
                                    $function = $this->validationMapping[$operation]['name'];
                                    $valid = $this->$function($param);
                                    if($valid === false){
                                        $message = "JSONValidator::validateMapping::Required:>:field:[$reqField] value:[" . $data[$reqField] . "] is Invalid";
                                        $this->handleError('Exception', $message);
                                    }
                                    $param = $valid;
                                } else {
                                    $message = "JSONValidator::validateMapping::Required::invalid operation:[$operation]";
                                    $this->handleError('Exception', $message);
                                }
                            }
                            $indexData[$reqField] = $valid;
                        } else{
                            $message = "JSONValidator::validateMapping::Required::reqField[$reqField] validationType:[".json_encode($validationType)."] invalid validation type";
                            $this->handleError('Exception', $message);
                        }
                    }
                }
            }
            if(array_key_exists('info', $mapping) && array_key_exists('post_op', $mapping['info'])) {
                if(array_key_exists($mapping['info']['post_op'], $this->validationMapping)){
                    $function = $this->validationMapping[$mapping['info']['post_op']]['name'];
                    $this->$function();
                } else if(strstr($mapping['info']['post_op'], '>')) {
                    $operations = explode('>', $mapping['info']['post_op']);
                    if(!sizeof($operations)) {
                        $message = "JSONValidator::validateMapping::post_op::Invalid multi-operation description[".json_encode($mapping['info']['post_op'])."]";
                        $this->handleError('Exception', $message);
                    }
                    $returnVal = null;
                    foreach($operations as $operation) {
                        if(array_key_exists($operation, $this->validationMapping)){
                            $function = $this->validationMapping[$operation]['name'];
                            $returnVal = $this->$function($returnVal);
                            if($returnVal === false){
                                $message = "JSONValidator::validateMapping::post_op::$function failed";
                                $this->handleError('Exception', $message);
                            }
                        } else {
                            $message = "JSONValidator::validateMapping::post_op::invalid operation:[$operation]";
                            $this->handleError('Exception', $message);
                        }
                    }
                } else{
                    $message = "JSONValidator::validateMapping::BAD post_op VALUE:: Trigger:[".json_encode($mapping['info']['post_op'])."]";
                    $this->handleError('Exception', $message);
                }
            }
        }else if(array_key_exists("info", $mapping) && array_key_exists("multi_array", $mapping['info']) && $mapping['info']['multi_array'] === true){
            $index = 0;
            foreach($data as $dataNode){
                if(array_key_exists('info', $mapping) && array_key_exists('pre_op', $mapping['info'])) {
                    if(array_key_exists($mapping['info']['pre_op'], $this->validationMapping)){
                        $function = $this->validationMapping[$mapping['info']['pre_op']]['name'];
                        $this->$function();
                    } else if(strstr($mapping['info']['pre_op'], '>')) {
                        $operations = explode('>', $mapping['info']['pre_op']);
                        if(!sizeof($operations)) {
                            $message = "JSONValidator::validateMapping::pre_op::multi::Invalid multi-operation description[".json_encode($mapping['info']['pre_op'])."]";
                            $this->handleError('Exception', $message);
                        }
                        $returnVal = null;
                        foreach($operations as $operation) {
                            if(array_key_exists($operation, $this->validationMapping)){
                                $function = $this->validationMapping[$operation]['name'];
                                $returnVal = $this->$function($returnVal);
                                if($returnVal === false){
                                    $message = "JSONValidator::validateMapping::multi::pre_op::$function failed";
                                    $this->handleError('Exception', $message);
                                }
                            } else {
                                $message = "JSONValidator::validateMapping::multi::pre_op::invalid operation:[$operation]";
                                $this->handleError('Exception', $message);
                            }
                        }
                    } else{
                        $message = "JSONValidator::validateMapping::multi::BAD pre_op VALUE:: Trigger:[".json_encode($mapping['info']['pre_op'])."]";
                        $this->handleError('Exception', $message);
                    }
                }
                if(array_key_exists("optional", $mapping)){
                    foreach($mapping['optional'] as $reqField=>$validationType){
                        if(array_key_exists($reqField, $dataNode)){
                            if(is_array($validationType)){
                                $this->validateMapping($mapping['optional'][$reqField], $dataNode[$reqField], $indexData[$index][$reqField]);
                            }else if(is_callable($validationType)){
                                $indexData[$index][$reqField] = $validationType($dataNode[$reqField]);
                            }else if(array_key_exists($validationType, $this->validationMapping)){
                                $function = $this->validationMapping[$validationType]['name'];
                                $valid = $this->$function($dataNode[$reqField]);
                                if($valid !== false){
                                    $indexData[$index][$reqField] = $valid;
                                }   
                            }else if(strstr($validationType, '>')) {
                                $operations = explode('>', $validationType);
                                if(sizeof($operations)) {
                                    $param = $dataNode[$reqField];
                                    $break = false;
                                    foreach($operations as $operation) {
                                        if(array_key_exists($operation, $this->validationMapping)){
                                            $function = $this->validationMapping[$operation]['name'];
                                            $valid = $this->$function($param);
                                            if($valid !== false){
                                                $param = $valid;
                                            } else {
                                                $message = "JSONValidator::validateMapping::multi::Optional:>:field:[$reqField] value:[" . $param . "] is Invalid";
                                                $this->handleError('Log', $message);
                                                $break = true;
                                                break;
                                            }
                                        } else {
                                            $message = "JSONValidator::validateMapping::multi::Optional::invalid operation:[$operation]";
                                            $this->handleError('Log', $message);
                                            $break = true;
                                            break;
                                        }
                                    }
                                    if(!$break) $indexData[$index][$reqField] = $valid;
                                } else {
                                    $message = "JSONValidator::validateMapping::multi::Optional::Invalid multi-operation description[".json_encode($validationType)."]";
                                    $this->handleError('Log', $message);
                                }
                            }
                        }
                    }
                }
                if(array_key_exists("required", $mapping)){
                    foreach($mapping['required'] as $reqField=>$validationType){
                        if(!array_key_exists($reqField, $dataNode)){
                            $message = "JSONValidator::validateMapping::multi::Required::reqField: $reqField is missing";
                            $this->handleError('Exception', $message);
                        }else{
                            if(is_array($validationType)){
                                $this->validateMapping($mapping['required'][$reqField], $dataNode[$reqField], $indexData[$index][$reqField]);
                            }else if(is_callable($validationType)){
                                $indexData[$index][$reqField] = $validationType($dataNode[$reqField]);
                            } else if(array_key_exists($validationType, $this->validationMapping)){
                                $function = $this->validationMapping[$validationType]['name'];
                                $valid = $this->$function($dataNode[$reqField]);
                                if($valid===false){
                                    $message = "JSONValidator::validateMapping::Required-multi::field:[$reqField] value:" . $dataNode[$reqField] . "is invalid";
                                    $this->handleError('Exception', $message);
                                }
                                $indexData[$index][$reqField] = $valid;
                            } else if(strstr($validationType, '>')) {
                                $operations = explode('>', $validationType);
                                if(!sizeof($operations)) {
                                    $message = "JSONValidator::validateMapping::multi::Required::Invalid multi-operation description[".json_encode($validationType)."]";
                                    $this->handleError('Exception', $message);
                                }
                                $param = $dataNode[$reqField];
                                foreach($operations as $operation) {
                                    if(array_key_exists($operation, $this->validationMapping)){
                                        $function = $this->validationMapping[$operation]['name'];
                                        $valid = $this->$function($param);
                                        if($valid === false){
                                            $message = "JSONValidator::validateMapping::Required-multi::>::field:[$reqField] value:" . $dataNode[$reqField] . "is invalid";
                                            $this->handleError('Exception', $message);
                                        }
                                        $param = $valid;
                                    } else {
                                        $message = "JSONValidator::validateMapping::multi::Required::invalid operation!:[$operation]";
                                        $this->handleError('Exception', $message);
                                    }
                                }
                                $indexData[$index][$reqField] = $valid;
                            } else {
                                $message = "JSONValidator::validateMapping::multi::Required::invalid validation type:[$validationType]";
                                $this->handleError('Exception', $message);
                            }
                        }
                    }
                }

                if(array_key_exists('info', $mapping) && array_key_exists('post_op', $mapping['info'])) {
                    if(array_key_exists($mapping['info']['post_op'], $this->validationMapping)){
                        $function = $this->validationMapping[$mapping['info']['post_op']]['name'];
                        $this->$function();
                    } else if(strstr($mapping['info']['post_op'], '>')) {
                        $operations = explode('>', $mapping['info']['post_op']);
                        if(!sizeof($operations)) {
                            $message = "JSONValidator::validateMapping::multi::post_op::Invalid multi-operation description[".json_encode($mapping['info']['post_op'])."]";
                            $this->handleError('Exception', $message);
                        }
                        $returnVal = null;
                        foreach($operations as $operation) {
                            if(array_key_exists($operation, $this->validationMapping)){
                                $function = $this->validationMapping[$operation]['name'];
                                $returnVal = $this->$function($returnVal);
                                if($returnVal === false){
                                    $message = "JSONValidator::validateMapping::multi::post_op::$function failed";
                                    $this->handleError('Exception', $message);
                                }
                            } else {
                                $message = "JSONValidator::validateMapping::multi::post_op::invalid operation:[$operation]";
                                $this->handleError('Exception', $message);
                            }
                        }
                    } else{
                        $message = "JSONValidator::validateMapping::multi::BAD post_op VALUE:: Trigger:[".json_encode($mapping['info']['post_op'])."]";
                        $this->handleError('Exception', $message);
                    }
                }

                $index++;
            }
        }
        return $indexData;
    }
}

