<?php
declare(strict_types = 1);
/**
 * /src/Command/LinksAddCommand.php
 *
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */
namespace App\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use function file_get_contents;
use function filter_var;
use function strpos;

/**
 * Class LinksAddCommand
 *
 * @package App\Command
 * @author  TLe, Tarmo Leppänen <tarmo.leppanen@protacon.com>
 */
class LinksAddCommand extends Command
{
    /**
     * The default command name
     *
     * @var string
     */
    protected static $defaultName = 'links:add';

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var Response
     */
    private $response;

    /**
     * LinksAddCommand constructor.
     *
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(ParameterBagInterface $parameterBag)
    {
        parent::__construct(self::$defaultName);

        $this->projectDir = $parameterBag->get('kernel.project_dir');
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Command to add new link to README.md file')
            ->addArgument('url', InputArgument::OPTIONAL, 'URL to add')
        ;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null
     *
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->io = new SymfonyStyle($input, $output);

        // Rebase current working copy with origin master
        $this->makeRebase($output);

        // Determine URL to use within this run time, after this we have a valid URL and `$this->response` to go on
        $url = $this->getUrl($input->getArgument('url'));

        $this->io->success('TODO: just implemented the rest of this URL: ' . $url);

        return null;
    }

    /**
     * @param OutputInterface $output
     *
     * @throws \Exception
     */
    private function makeRebase(OutputInterface $output): void
    {
        $command = $this->getApplication()->find('rebase');
        $command->run(new ArrayInput([]), $output);
    }

    /**
     * @param null|string $url
     *
     * @return string
     */
    private function getUrl(?string $url): string
    {
        while (!$this->isValidUrl($url)) {
            if ($url !== null) {
                $this->io->warning('Given URL isn\'t valid');
            }

            $question = new Question('Give an URL to add to README.md', $url);

            $url = (string)$this->io->askQuestion($question);
        }

        return $url;
    }

    /**
     * @param null|string $url
     *
     * @return bool
     */
    private function isValidUrl(?string $url): bool
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);

        return filter_var($url, FILTER_VALIDATE_URL)
            && $this->makeRequest($url)
            && $this->checkIfUrlAlreadyExists($url) === false;
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    private function makeRequest(string $url): bool
    {
        $output = false;
        $client = new Client();
        $request = new Request('GET', $url);

        try {
            $this->response = $client->send($request, ['timeout' => 10]);

            if ($this->response->getStatusCode() !== 200) {
                throw new RequestException($this->response->getReasonPhrase(), $request, $this->response);
            }

            $output = true;
        } catch (RequestException $exception) {
            $message = $exception->getMessage();

            if ($exception->hasResponse()) {
                $response = $exception->getResponse();

                /** @noinspection NullPointerExceptionInspection */
                $message = $response->getStatusCode() . ' - ' . $response->getReasonPhrase();
            }

            $this->io->warning($message);
        } catch (GuzzleException $exception) {
            $this->io->warning($exception->getMessage());
        }

        return $output;
    }

    /**
     * Method to check if URL already exists on current README.md
     *
     * @param string $url
     *
     * @return bool
     */
    private function checkIfUrlAlreadyExists(string $url): bool
    {
        $output = false;
        $contents = file_get_contents($this->projectDir . DIRECTORY_SEPARATOR . 'README.md');

        if (strpos($contents, $url) !== false) {
            $this->io->warning('This URL already exists on README.md');

            $output = true;
        }

        return $output;
    }
}
