<?php

namespace Phpactor\UserInterface\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phpactor\Application\ClassCopy;
use Symfony\Component\Console\Input\InputArgument;
use Phpactor\Phpactor;
use Phpactor\UserInterface\Console\Logger\SymfonyConsoleCopyLogger;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Phpactor\UserInterface\Console\Prompt\Prompt;
use Phpactor\Application\ClassNew;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Phpactor\UserInterface\Console\Dumper\DumperRegistry;
use Phpactor\Application\Exception\FileAlreadyExists;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Phpactor\Application\ClassInflect;

class ClassInflectCommand extends Command
{
    /**
     * @var DumperRegistry
     */
    private $dumperRegistry;

    /**
     * @var ClassNew
     */
    private $classInflect;

    public function __construct(
        ClassInflect $classInflect,
        DumperRegistry $dumperRegistry
    ) {
        parent::__construct();
        $this->dumperRegistry = $dumperRegistry;
        $this->classInflect = $classInflect;
    }

    public function configure()
    {
        $this->setName('class:inflect');
        $this->setDescription('Inflect new class from existing path or FQN');
        $this->addArgument('src', InputArgument::REQUIRED, 'Source path or FQN');
        $this->addArgument('dest', InputArgument::REQUIRED, 'Destination path or FQN');
        $this->addArgument('variant', null, InputOption::VALUE_REQUIRED, 'Variant', 'default');
        $this->addOption('list', null, InputOption::VALUE_NONE, 'List variants');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Force overwriting');
        Handler\FormatHandler::configure($this);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('list')) {
            return $this->listGenerators($input, $output);
        }

        $out = $this->process($input, $output);
        $this->dumperRegistry->get($input->getOption('format'))->dump($output, $out);
    }

    private function process(InputInterface $input, OutputInterface $output)
    {
        $src = $input->getArgument('src');
        $dest = $input->getArgument('dest');
        $variant = $input->getArgument('variant');
        $response = [
            'src' => $src,
            'dest' => $dest,
            'path' => null,
            'exists' => false,
        ];

        try {
            $response['path'] = $this->classInflect->generateFromExisting($src, $dest, $variant, $input->getOption('force'));
        } catch (FileAlreadyExists $exception) {
            $questionHelper = new QuestionHelper();
            $question = new ConfirmationQuestion('<question>File already exists, overwrite? [y/n]</>', false);

            if (false === $questionHelper->ask($input, $output, $question)) {
                $response['exists'] = true;
                return $response;
            }

            $filePath = $this->classInflect->generateFromExisting($src, $dest, $variant, true);
            $response['path'] = $filePath;
        }

        return $response;
    }

    private function listGenerators(InputInterface $input, OutputInterface $output)
    {
        $dumper = $this->dumperRegistry->get($input->getOption('format'));
        $dumper->dump($output, $this->classInflect->availableGenerators());
    }
}
