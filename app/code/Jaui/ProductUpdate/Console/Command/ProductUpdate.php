<?php
declare(strict_types=1);

namespace Jaui\ProductUpdate\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Jaui\ProductUpdate\Model\CsvProductUpdater;

class ProductUpdate extends Command
{
    private const FILEPATH = 'filepath';
    protected $CsvProductUpdater;

    public function __construct(
        CsvProductUpdater $CsvProductUpdater,
    ) {
        $this->CsvProductUpdater = $CsvProductUpdater;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('jaui:productupdate')
             ->setDescription('Update product information by importing data from CSV file.');
        $this->addOption(
            self::FILEPATH,
            null,
            InputOption::VALUE_REQUIRED,
            'Name'
        );
        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $exitCode = 0;

        $csvFilePath = $input->getOption(self::FILEPATH);

        $result = $this->CsvProductUpdater->updateProductsFromCsv($csvFilePath);

        if ($result) {
                echo "Products updated successfully \n";
        } else {
                echo "Error occurred during the update, handle the error \n";
        }

        return $exitCode;
    }
    
}
