<?php

namespace ProvisionOps\Tools;

use Psr\Log\LoggerAwareTrait;
use Robo\Common\OutputAwareTrait;
use Robo\Common\TimeKeeper;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Process\Process as BaseProcess;

class PowerProcess extends BaseProcess {

    /**
     * @var Style
     */
    public $io;
    public $successMessage = 'Process Succeeded';
    public $failureMessage = 'Process Failed';
    public $duration = '';

    /**
     * @param $io Style
     */
    public function setIo(Style $io) {
        $this->io = $io;
    }

    /**
     * PowerProcess constructor.
     * @param string $commandline
     * @param Style $io
     * @param null $cwd
     * @param array|null $env
     * @param null $input
     * @param int $timeout
     * @param array $options
     */
    public function __construct($commandline, $io, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = array())
    {
        $this->setIo($io);

        // Detect Symfony Process 4.x and up: if so, make $commandline an array.
        if (property_exists(BaseProcess::class, 'STATUS_READY')) {
            // @TODO: What is better? a single item array? or should we explode by spaces?
            $commandline = [$commandline];
        }
        parent::__construct($commandline, $cwd, $env, $input, $timeout, $options);
    }


    /**
     * Runs the process.
     *
     * @param callable|null $callback A PHP callback to run whenever there is some
     *                                output available on STDOUT or STDERR
     *
     * @return int The exit status code
     *
     * @throws RuntimeException When process can't be launched
     * @throws RuntimeException When process stopped after receiving signal
     * @throws LogicException   In case a callback is provided and output has been disabled
     *
     * @final
     */
    public function run($callback = null, array $env = [])
    {
        $this->io->writeln(" <comment>$</comment> {$this->getCommandLine()} <fg=black>Output:/path/to/file</>");

        $timer = new TimeKeeper();
        $timer->start();

        if ($this->io->isDebug()) {
          $this->io->table(["Execution Environment"], $this->getEnvTableRows());
        }

        $exit = parent::run(function ($type, $buffer) {
          if (getenv('PROVISION_PROCESS_OUTPUT') == 'direct') {
            echo $buffer;
          }
          else {
            $lines = explode("\n", $buffer);
            foreach ($lines as $line) {
              $this->io->outputBlock(trim($line), false, false);
            }
          }
        }, $env);

        $timer->stop();
        $this->duration = $timer->formatDuration($timer->elapsed());

        // @TODO: Optionally print something helpful but hideable here.
        // $suffix = "<fg=black>Output: /path/to/file</>";
        $suffix = '';

        if ($exit == 0) {
            $this->io->newLine();
            $this->io->writeln(" <info>✔</info> {$this->successMessage} in {$this->duration} $suffix");
        }
        else {
            $this->io->newLine();
            $this->io->writeln(" <fg=red>✘</> {$this->failureMessage} in {$this->duration} {$suffix}");
        }

        return $exit;

    }

    /**
     * Provides compatibility with Process 3.x.
     * @inheritDoc
     */
    public function mustRun($callback = null /*, array $env = []*/) {
        $env = 1 < \func_num_args() ? func_get_arg(1) : null;
        parent::mustRun($callback, $env);
    }

    /**
     * Convert the execution environment to table row arrays.
     */
    public function getEnvTableRows() {
      $rows = [];
      foreach ($this->getEnv() as $name => $value) {
        $rows[] = [$name, $value];
      }
      return $rows;
    }
}
