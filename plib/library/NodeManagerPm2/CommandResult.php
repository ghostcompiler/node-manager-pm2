<?php
class Modules_NodeManagerPm2_CommandResult
{
    public $code;
    public $stdout;
    public $stderr;
    public $command;

    public function __construct($code, $stdout, $stderr, $command)
    {
        $this->code = (int) $code;
        $this->stdout = (string) $stdout;
        $this->stderr = (string) $stderr;
        $this->command = (string) $command;
    }

    public function assertOk($message)
    {
        if ($this->code !== 0) {
            $details = trim($this->stderr) ?: trim($this->stdout);
            throw new Modules_NodeManagerPm2_Exception($message . ($details ? ': ' . $details : ''));
        }

        return $this;
    }
}
