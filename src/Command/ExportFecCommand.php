<?php

declare(strict_types=1);

namespace CorentinBoutillier\InvoiceBundle\Command;

use CorentinBoutillier\InvoiceBundle\Provider\CompanyProviderInterface;
use CorentinBoutillier\InvoiceBundle\Service\Fec\FecExporterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Export invoices to FEC (Fichier des Écritures Comptables) format.
 *
 * French legal accounting export format for tax compliance (Article A.47 A-1 LPF).
 *
 * Usage:
 *   php bin/console invoice:export-fec 2024
 *   php bin/console invoice:export-fec 2024 --output=/path/to/file.txt
 *   php bin/console invoice:export-fec 2024 --company-id=1
 */
#[AsCommand(
    name: 'invoice:export-fec',
    description: 'Export invoices to FEC format for French tax compliance',
)]
final class ExportFecCommand extends Command
{
    /** Minimum acceptable fiscal year (prevent typos). */
    private const MIN_FISCAL_YEAR = 2000;

    /** Maximum acceptable fiscal year (prevent future typos). */
    private const MAX_FISCAL_YEAR = 2100;

    public function __construct(
        private readonly FecExporterInterface $fecExporter,
        private readonly CompanyProviderInterface $companyProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
The <info>invoice:export-fec</info> command exports finalized invoices to FEC format.

FEC (Fichier des Écritures Comptables) is the French legal accounting export format
required for tax compliance (Article A.47 A-1 du Livre des Procédures Fiscales).

<info>Usage:</info>

  # Export fiscal year 2024 to stdout
  <comment>php bin/console invoice:export-fec 2024</comment>

  # Export to specific file
  <comment>php bin/console invoice:export-fec 2024 --output=/path/to/FEC_2024.txt</comment>

  # Export for specific company (multi-company setups)
  <comment>php bin/console invoice:export-fec 2024 --company-id=1</comment>

<info>Fiscal Year Calculation:</info>

The command automatically calculates fiscal year start/end dates based on your
fiscal_year_start_month configuration. For example:
  - Config: fiscal_year_start_month = 1 (January)
    Fiscal 2024 = 2024-01-01 to 2024-12-31
  - Config: fiscal_year_start_month = 11 (November)
    Fiscal 2024 = 2024-11-01 to 2025-10-31

<info>Output Format:</info>

The output is a pipe-separated (|) CSV file with 18 mandatory columns per the
French legal standard. Only FINALIZED invoices are included.
HELP
            )
            ->addArgument(
                'fiscal-year',
                InputArgument::REQUIRED,
                'Fiscal year to export (YYYY format, e.g., 2024)',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path (if omitted, writes to stdout)',
            )
            ->addOption(
                'company-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Company ID filter for multi-company setups',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Parse and validate fiscal year
        /** @var string $fiscalYearInput */
        $fiscalYearInput = $input->getArgument('fiscal-year');
        $fiscalYear = $this->parseFiscalYear($fiscalYearInput, $io);

        if (null === $fiscalYear) {
            return Command::FAILURE;
        }

        // 2. Calculate fiscal year start/end dates
        [$startDate, $endDate] = $this->calculateFiscalYearDates($fiscalYear);

        // 3. Parse company ID option
        $companyId = $this->parseCompanyId($input);

        // 4. Call FecExporter to generate export content
        $fecContent = $this->fecExporter->export($startDate, $endDate, $companyId);

        // 5. Write to file or stdout
        /** @var string|null $outputPath */
        $outputPath = $input->getOption('output');

        if (null !== $outputPath) {
            $this->writeToFile($outputPath, $fecContent, $io);
        } else {
            $this->writeToStdout($fecContent, $output);
        }

        return Command::SUCCESS;
    }

    /**
     * Parse and validate fiscal year input.
     *
     * @return int|null Fiscal year (YYYY) or null if invalid
     */
    private function parseFiscalYear(string $input, SymfonyStyle $io): ?int
    {
        // Check if input is numeric
        if (!ctype_digit($input)) {
            $io->error(\sprintf('Invalid fiscal year format: "%s". Expected 4-digit year (e.g., 2024).', $input));

            return null;
        }

        $year = (int) $input;

        // Validate year is in reasonable range
        if ($year < self::MIN_FISCAL_YEAR || $year > self::MAX_FISCAL_YEAR) {
            $io->error(\sprintf(
                'Fiscal year %d is out of acceptable range (%d-%d).',
                $year,
                self::MIN_FISCAL_YEAR,
                self::MAX_FISCAL_YEAR,
            ));

            return null;
        }

        return $year;
    }

    /**
     * Calculate fiscal year start/end dates based on configuration.
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable} [startDate, endDate]
     */
    private function calculateFiscalYearDates(int $fiscalYear): array
    {
        // Get company data to retrieve fiscal year configuration
        $companyData = $this->companyProvider->getCompanyData();
        $startMonth = $companyData->fiscalYearStartMonth;

        // Calculate start date (first day of start month in fiscal year)
        $startDate = new \DateTimeImmutable(\sprintf('%d-%02d-01', $fiscalYear, $startMonth));

        // Calculate end date (last day of month before next fiscal year starts)
        $endDate = $startDate->modify('+12 months')->modify('-1 day');

        return [$startDate, $endDate];
    }

    /**
     * Parse company ID option.
     */
    private function parseCompanyId(InputInterface $input): ?int
    {
        /** @var string|null $companyIdInput */
        $companyIdInput = $input->getOption('company-id');

        if (null === $companyIdInput) {
            return null;
        }

        if (!ctype_digit($companyIdInput)) {
            return null;
        }

        return (int) $companyIdInput;
    }

    /**
     * Write FEC content to file.
     */
    private function writeToFile(string $path, string $content, SymfonyStyle $io): void
    {
        // Create directory if it doesn't exist
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        // Write content to file
        file_put_contents($path, $content);

        $io->success(\sprintf('FEC export successfully written to: %s', $path));
    }

    /**
     * Write FEC content to stdout.
     */
    private function writeToStdout(string $content, OutputInterface $output): void
    {
        $output->write($content);
    }
}
