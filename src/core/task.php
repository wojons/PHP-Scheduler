<?php

require_once(dirname(__FILE__).'/event.php');
require_once(dirname(__FILE__).'/fileDescriptor.php');

class task {
    
    public $waitFor    = null;
    public $proxyValue = array();
    public $super      = array();
    public $pending_fd = array(); //so people know the fd needs reads or writes
    public $fd_table;
    
    private $state;
    private $post_run = array();
    private $task_id  = null;
    private $finished = False;
    private $b4Yield  = True;
    private $incomingValue = array();
    private $name = null;
    private $ptid = null; //parent task id
    
    function __construct($opt=null, callable $script) {
        $this->script = $script;
        $this->name = (isset($opt['name'])) ? $opt['name'] : null;
        $this->fd_table = (new fileDescriptor);
    }
    
    function __destruct() {
        //print "killing task".PHP_EOL;
    }
    
    function setTaskId($id) {
        $this->task_id = $id;
    }
    
    function getTaskId() {
        return $this->task_id;
    }
    
    function getId() {
        return $this->getTaskId();
    }
    
    function setState ($state='active') {
        $this->state = 'active';
    }
    
    function isStateActive() {
        return ($this->state == 'active') ? True : False;
    }
    
    function setProxyValue($value) {
        $this->proxyValue[] = $value;
    }
    
    function proxyValueIsSet() {
        if(!empty($this->proxyValue)) {
            return True;
        }
        return False;
    }
    
    function getProxyValue($clear=false) {
        if($clear == True) {
            $r = $this->proxyValue;
            $this->proxyValue = array();
            return $r;
        } else {
            return $this->proxyValue;
        }
    }
    
    function setParentId($ptid) {
        if($this->ptid === Null || $ptid === Null) {
            $this->ptid = $ptid;
            return True;
        }
        return False;
    }
    
    function getParentId()  {
        return $this->ptid;
    }
    
    private function receiveValue() {
        $v = $this->incomingValue;
        $this->incomingValue = array();
        return $v;
    }
    
    public function setRetval($val) {
        $this->retval[] = $val;
    }
    
    private function getRetval() {
        if(!empty($this->retval)) {
            $r = $this->retval;
            $this->retval = array();
            return $r;
        }
        return null;
    }
    
    function run() {
        if(!$this->loopCheck() || !$this->sleepCheck()) {
            return False;
        }
        if(!empty($this->events)) {
            foreach($this->events as $eId => $event ) {
                $e = $event->run();
                if($e !== Null) {
                    $this->setRetval($e);
                }
                
                if(!$event->isValid()) {
                    unset($this->events[$eId]);
                }
            }
        }
        
        $this->script->__invoke($this);
        return $this->getRetval();
    }
    
    function addEvent(event $event, $name=null) {
        if(isset($name)) {
            $event->name = $name;
        }
        $this->events[] = $event;
        return True;
    }
    
    function delEvent($id) {
        unset($this->events[$id]);
    }
    
    function bypassRun($send) {
        return $this->__processReturn($this->corutine->send($send));
    }
    
    function isFinished()   {
        if($this->finished == True) {
            return True;
        }
        return False;
    }
    
    function sleepCheck() {
        if(isset($this->sleepTill)) {
            if($this->sleepTill > time()) {
                return False;
            }
            $this->sleepTill = Null;
        }
        return True;
    }
    
    function loopCheck() {
        if(isset($this->sleepLoop) && $this->sleepLoop > 0) {
            $this->sleepLoop--;
            return False;
        }
        return True;
    }
    
    function setFinshed($state) {
        if (is_bool($state)) {
            $this->finished = $state;
            return True;
        }
        return False;
    }
    
    function sleep($time) {
        if(is_int($time) && $time > 0) {
            $time->sleepUntil($time+time());
            return True;
        }
        return False;
    }
    
    function sleepUntil($until) {
        if($until-time() > 0) {
            $this->sleepTill = $until;
            return True;
        }
        return False;
    }
    
    //sleep for this many passes
    function sleepLoop($loops) {
        if(is_int($loops) && $loops > 0) {
            $this->sleepLoop = (int)$loops;
            return True;
        };
        return False;
    }
    
    function __processReturn($re) {
        foreach($re as $dex=>$dat) {
            if($dat instanceof task) {
                $toSchedular[] = $dat;
            }
            elseif($dat instanceof systemCall) {
                $toScheduler[] = $dat;
            }
        }
        return $toScheduler;
    }
    
    function __invoke() {
        return $this->run();
    }
    
    function __delSelf() {
        if(!empty($this->events)) foreach($this->events as $dex=>$dat) {
            $dat->__delSelf();
            unset($this->events[$dex]);
        }
    }
    
}

?>
