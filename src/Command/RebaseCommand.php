<?php
declare(strict_types = 1);
/**
 * /src/Command/LinksAddCommand.php
 *
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */

namespace App\Command;

use Cz\Git\GitRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use function file_get_contents;
use function in_array;
use function json_decode;

/**
 * Class RebaseCommand
 *
 * @package App\Command
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */
class RebaseCommand extends Command
{
    protected static $defaultName = 'rebase';

    /**
     * @var \Cz\Git\GitRepository
     */
    private $repository;

    /**
     * @var string
     */
    private $remoteUrl;

    /**
     * RebaseCommand constructor.
     *
     * @param ParameterBagInterface $parameterBag
     *
     * @throws \Cz\Git\GitException
     */
    public function __construct(ParameterBagInterface $parameterBag)
    {
        parent::__construct(self::$defaultName);

        $projectDir = $parameterBag->get('kernel.project_dir');
        $composerJson = file_get_contents($projectDir. DIRECTORY_SEPARATOR . 'composer.json');

        $this->remoteUrl = json_decode($composerJson)->extra->repositoryUrl;
        $this->repository = new GitRepository($projectDir);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription('Command to rebase your current code with remote master');
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws \Cz\Git\GitException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $question = new Question('Do you want to rebase current local repository to origin-upstream', 'yes');

        if ($io->askQuestion($question) === 'yes') {
            if (!in_array('origin-upstream', $this->repository->execute('remote'), true)) {
                $io->note('origin-upstream remote is not set yet, adding that to remotes.');

                $this->repository->addRemote('origin-upstream', $this->remoteUrl);
            } else {
                $io->success(
                    'origin-upstream (' . $this->remoteUrl . ') has been set to remotes, no need to add that again.'
                );
            }

            $this->repository->fetch('origin-upstream');
            $io->success('Fetched changes from origin-upstream');

            $this->repository->execute(['rebase', '--autostash', 'origin-upstream/master']);
            $io->success('Rebase done with origin-upstream (' . $this->remoteUrl . ')');
        }

        return 0;
    }
}
