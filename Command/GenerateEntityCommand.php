<?php

namespace CodeAge\EntityGeneratorBundle\Command;

use CodeAge\EntityGeneratorBundle\Base\FileGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 
 *
 * @author Paweł
 */
class GenerateEntityCommand extends ContainerAwareCommand {

    private $types = array('string', 'integer', 'boolean', 'date', 'datetime', 'time', 'text', 'float', 'decimal', 'entity');
    private $bundles = array();
    private $tables = array();
    private $tablesNames = array();

    /**
     * Metoda konfigurująca komendę.
     */
    protected function configure() {
        $this
                ->setName('ca:entity:generate')
                ->setDescription('Generates entity using entity-model-manager architecture.');
    }

    /**
     * Polecenie 
     * 
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

        $io = new SymfonyStyle($input, $output);

        $em = $this->getContainer()->get('doctrine')->getManager();
        $tables = $em->getMetadataFactory()->getAllMetadata();
        foreach ($tables as $table) {
            $this->tablesNames[] = $table->table['name'];
            $this->tables[$table->table['name']] = $table->getName();
        }
        $bundles = $this->getBundlesNames();

        //Generowanie pytań.
        $dialog = $this->getHelper('question');
        $bundleNameQuestion = new Question('<fg=green>Please enter entity name eg. YourBundle:EntityName' . PHP_EOL . 'Entity name::</fg=green> ', false);
        $bundleNameQuestion->setAutocompleterValues($bundles);

        $fieldNameQuestion = new Question($this->printQuestion("Insert field name", false, "Leave empty and press enter to stop"), false);

        $fieldTypeQuestion = new Question($this->printQuestion("Field type", false, "String"), 'string');
        $fieldTypeQuestion->setAutocompleterValues($this->types);


        $bundleName = $dialog->ask($input, $output, $bundleNameQuestion);
        $bundleData = $this->parseBundleName($bundleName, $output);
        $fields = array();
        $stop = false;
        while (!$stop) {
            $field = array();
            $fieldName = $dialog->ask($input, $output, $fieldNameQuestion);
            if ($fieldName == '') {
                $stop = true;
            } else {
                $field['name'] = $fieldName;
                $output->writeln("Available types: " . implode(", ", $this->types));
                $fieldType = $dialog->ask($input, $output, $fieldTypeQuestion);
                if (!in_array($fieldType, $this->types)) {
                    $field['type'] = 'string';
                } else {
                    $field['type'] = $fieldType;
                }
                $attributes = $this->getFieldTypeConfiguration($field['type'], $input, $output, $dialog);
                $field['attributes'] = $attributes;
                $fields[] = $field;
            }
        }
        $data = array(
            'class' => $bundleData['className'],
            'namespace' => $bundleData['namespace'],
            'bundle' => $bundleName,
            'fields' => $fields
        );

        $this->displayResult($data, $output);

        $formatter = $this->getHelper('formatter');

        $confirmationQuestion = new ConfirmationQuestion('Confirm generation?', false);
        if (!$dialog->ask($input, $output, $confirmationQuestion)) {
            $io->error('Generating files aborted.');
            return;
        }

        $fileGenerator = new FileGenerator($data);
        $fileGenerator->generateModel();
        $fileGenerator->generateEntity();
        $fileGenerator->generateManager();
        $fileGenerator->generateService();
        $fileGenerator->generateOrm();

//        $operationResult = $formatter->formatBlock(
//                '[OK] Finished creating files for entity ' . $data['bundle'], 'info'
//        );
        $io->success('Finished creating files for entity ' . $data['bundle']);
//        $output->writeln($operationResult);
    }

    private function displayResult($data, $output) {
        $table = new Table($output);
        $table->setHeaders(array(
            'Field name', 'Field type', 'Length', 'Nullable', 'Precision', 'Scale', 'Relation'
        ));

        $rows = array();
        foreach ($data['fields'] as $field) {
            $row = array();
            $row[] = $field['name'];
            if ($field['type'] == 'entity') {
                switch ($field['attributes']['relation']['type']) {
                    case 'm2o': $row[] = 'Relation:: many-to-one';
                        break;
                    case 'o2o': $row[] = 'Relation:: one-to-one';
                        break;
                    case 'm2m': $row[] = 'Relation:: many-to-many';
                        break;
                    case 'o2m': $row[] = 'Relation:: one-to-many';
                        break;
                }
            } else {
                $row[] = $field['type'];
            }
            $row[] = (isset($field['attributes']['length'])) ? $field['attributes']['length'] : '---';
            $row[] = (isset($field['attributes']['nullable'])) ? (($field['attributes']['nullable']) ? '<fg=green>true</>' : '<fg=red>false</>') : '---';
            $row[] = (isset($field['attributes']['precision'])) ? $field['attributes']['precision'] : '---';
            $row[] = (isset($field['attributes']['scale'])) ? $field['attributes']['scale'] : '---';
            if (isset($field['attributes']['relation']['type'])) {
                $row[] = $field['attributes']['relation']['class'];
            } else {
                $row[] = '---';
            }
            $rows[] = $row;
        }
        $table->setRows($rows);
        $table->render();
    }

    private function parseBundleName($bundleName, $output) {
        $parseData = array();
        if (strpos($bundleName, ":") !== false) {
            $data = explode(":", $bundleName);
            if (isset($this->bundles[$data[0]])) {
                $parseData['className'] = $data[1];
                $namespace = preg_match('/(?<namespace>.*?)\\\\[^\\\\]*?$/', $this->bundles[$data[0]], $matches);
                $parseData['namespace'] = $matches['namespace'];
                return $parseData;
            } else {
                $output->writeln("<error>Bundle does not exist.</error>");
                die;
            }
        } else {
            $output->writeln("<error>Entity name is not valid.</error>");
            die;
        }
    }

    private function getBundlesNames() {
        $bundles = $this->getContainer()->getParameter('kernel.bundles');
        $this->bundles = $bundles;
        $bundlesNames = array();
        foreach ($bundles as $bundleName => $bundlePath) {
            $bundlesNames[] = $bundleName;
        }
        return $bundlesNames;
    }

    private function getFieldTypeConfiguration($type, $input, $output, $dialog) {
        $attributes = array();
        switch ($type) {
            case 'string':
                $attributes['length'] = $this->getLength($input, $output, $dialog);
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'integer':
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'boolean':
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'date':
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'datetime':
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'time':
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'text':
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'float':
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'decimal':
                $attributes['precision'] = $this->getPrecision($input, $output, $dialog);
                $attributes['scale'] = $this->getScale($input, $output, $dialog);
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
            case 'entity':
                $attributes['relation'] = $this->getRelationType($input, $output, $dialog);
                $attributes['nullable'] = $this->getNullable($input, $output, $dialog);
                break;
        }
        return $attributes;
    }

    private function getLength($input, $output, $dialog) {
        $question = new Question($this->printQuestion("Length", true, "255"), false);
        $length = $dialog->ask($input, $output, $question);
        $length = (int) $length;
        if ($length == 0 || $length > 255) {
            $length = 255;
        }
        return $length;
    }

    private function getPrecision($input, $output, $dialog) {
        $question = new Question($this->printQuestion("Precision", true, "10"), false);
        $precision = $dialog->ask($input, $output, $question);
        $precision = (int) $precision;
        if ($precision == 0) {
            $precision = 10;
        }
        return $precision;
    }

    private function getScale($input, $output, $dialog) {
        $question = new Question($this->printQuestion("Scale", true, "0"), false);
        $scale = $dialog->ask($input, $output, $question);
        $scale = (int) $scale;
        return $scale;
    }

    private function getNullable($input, $output, $dialog) {
        $question = new Question($this->printQuestion("Nullable", true, "false"), false);
        $nullable = $dialog->ask($input, $output, $question);
        if ($nullable == 'true') {
            $nullable = true;
        } else {
            $nullable = false;
        }
        return $nullable;
    }

    private function getRelationType($input, $output, $dialog) {
        $relationTypes = array('o2o', 'o2m', 'm2o', 'm2m');
        $question = new Question($this->printQuestion("Relation type (o2o|o2m|m2o|m2m)", true, "o2o"), false);
        $question->setAutocompleterValues($relationTypes);
        $type = $dialog->ask($input, $output, $question);
        if (!in_array($type, $relationTypes)) {
            $type = 'o2o';
        }
        $tableClass = $this->getRelationTable($input, $output, $dialog);
        $return = array(
            'type' => $type,
            'class' => $tableClass
        );
        return $return;
    }

    private function getRelationTable($input, $output, $dialog) {
        $question = new Question($this->printQuestion("Enter relation table name"), false);
        $question->setAutocompleterValues($this->tablesNames);
        $table = $dialog->ask($input, $output, $question);
        if (!in_array($table, $this->tablesNames)) {
            $tableClass = $table;
        } else {
            $tableClass = $this->tables[$table];
        }
        return $tableClass;
    }

    private function printQuestion($question, $indent = false, $default = false) {
        if ($indent) {
            $indent = "\t";
        } else {
            $indent = "";
        }
        if ($default) {
            $default = "[<fg=yellow>" . $default . "</fg=yellow>]";
        } else {
            $default = "";
        }
        return "<fg=green>" . $indent . $question . " </fg=green>" . $default . "<fg=green>::</fg=green> ";
    }

}
