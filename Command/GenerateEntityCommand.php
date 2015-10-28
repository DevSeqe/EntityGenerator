<?php

namespace CodeAge\EntityGeneratorBundle\Command;

use CodeAge\EntityGeneratorBundle\Base\FileGenerator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        //Pobieranie wszystkich encji z aplikacji
                
        $em = $this->getContainer()->get('doctrine')->getManager();
        $tables = $em->getMetadataFactory()->getAllMetadata();
        foreach ($tables as $table)
        {
            $this->tablesNames[] = $table->table['name'];
            $this->tables[$table->table['name']] = $table->getName();
        }

        $bundles = $this->getBundlesNames();
        $dialog = $this->getHelper('dialog');
        $bundleName = $dialog->ask(
                $output, '<fg=green>Please enter entity name eg. YourBundle:EntityName' . PHP_EOL . 'Entity name::</fg=green> ', false, $bundles
        );
        $bundleData = $this->parseBundleName($bundleName, $output);
        $fields = array();
        $stop = false;
        while (!$stop)
        {
            $field = array();
            $fieldName = $dialog->ask(
                    $output, $this->printQuestion("Insert field name", false, "Leave empty and press enter to stop")
            );
            if ($fieldName == '')
            {
                $stop = true;
            } else
            {
                $field['name'] = $fieldName;
                $output->writeln("Available types: ".implode(", ",$this->types));
                $fieldType = $dialog->ask(
                        $output, $this->printQuestion("Field type", false, "String"), 'string', $this->types
                );
                if (!in_array($fieldType, $this->types))
                {
                    $field['type'] = 'string';
                } else
                {
                    $field['type'] = $fieldType;
                }
                $attributes = $this->getFieldTypeConfiguration($field['type'], $output, $dialog);
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
        $fileGenerator = new FileGenerator($data);
        $fileGenerator->generateModel();
        $fileGenerator->generateEntity();
        $fileGenerator->generateManager();
        $fileGenerator->generateService();
        $fileGenerator->generateOrm();
        die;
        $output->writeln('This command will generate ' . $bundleName . ' entity.');
    }
    
    private function parseBundleName($bundleName, $output){
        $parseData = array();
        if(strpos($bundleName,":") !== false){
            $data = explode(":",$bundleName);
            if(isset($this->bundles[$data[0]])){
                $parseData['className'] = $data[1];
                $namespace = preg_match('/(?<namespace>.*?)\\\\[^\\\\]*?$/', $this->bundles[$data[0]],$matches);
                $parseData['namespace'] = $matches['namespace'];
                return $parseData;
            }
            else{
                $output->writeln("<error>Bundle does not exist.</error>");
                die;
            }
        }
        else{
            $output->writeln("<error>Entity name is not valid.</error>");
            die;
        }
    }

    private function getBundlesNames() {
        $bundles = $this->getContainer()->getParameter('kernel.bundles');
        $this->bundles = $bundles;
        $bundlesNames = array();
        foreach ($bundles as $bundleName => $bundlePath)
        {
            $bundlesNames[] = $bundleName;
        }
        return $bundlesNames;
    }

    private function getFieldTypeConfiguration($type, $output, $dialog) {
        $attributes = array();
        switch ($type) {
            case 'string':
                $attributes['length'] = $this->getLength($output, $dialog);
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'integer':
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'boolean':
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'date':
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'datetime':
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'time':
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'text':
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'float':
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'decimal':
                $attributes['precision'] = $this->getPrecision($output, $dialog);
                $attributes['scale'] = $this->getScale($output, $dialog);
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
            case 'entity':
                $attributes['relation'] = $this->getRelationType($output, $dialog);
                $attributes['nullable'] = $this->getNullable($output, $dialog);
                break;
        }
        return $attributes;
    }

    private function getLength($output, $dialog) {
        $length = $dialog->ask(
                $output, $this->printQuestion("Length", true, "255")
        );
        $length = (int) $length;
        if ($length == 0 || $length > 255)
        {
            $length = 255;
        }
        return $length;
    }

    private function getPrecision($output, $dialog) {
        $precision = $dialog->ask(
                $output, $this->printQuestion("Precision", true, "10")
        );
        $precision = (int) $precision;
        if ($precision == 0)
        {
            $precision = 10;
        }
        return $precision;
    }

    private function getScale($output, $dialog) {
        $scale = $dialog->ask(
                $output, $this->printQuestion("Scale", true, "0")
        );
        $scale = (int) $scale;
        return $scale;
    }

    private function getNullable($output, $dialog) {
        $nullable = $dialog->ask(
                $output, $this->printQuestion("Nullable", true, "false")
        );
        if ($nullable == 'true')
        {
            $nullable = true;
        } else
        {
            $nullable = false;
        }
        return $nullable;
    }

    private function getRelationType($output, $dialog) {
        $relationTypes = array('o2o', 'o2m', 'm2o', 'm2m');
        $type = $dialog->ask(
                $output, $this->printQuestion("Relation type (o2o|o2m|m2o|m2m)", true, "o2o"), false, $relationTypes
        );
        if (!in_array($type, $relationTypes))
        {
            $type = 'o2o';
        }
        $tableClass = $this->getRelationTable($output, $dialog);
        $return = array(
            'type' => $type,
            'class' => $tableClass
        );
        return $return;
    }

    private function getRelationTable($output, $dialog) {
        $table = $dialog->ask(
                $output, $this->printQuestion("Enter relation table name"), false, $this->tablesNames
        );
        if (!in_array($table, $this->tablesNames))
        {
            $tableClass = $table;
        }
        else{
            $tableClass = $this->tables[$table];
        }
        return $tableClass;
    }

    private function printQuestion($question, $indent = false, $default = false) {
        if ($indent)
        {
            $indent = "\t";
        } else
        {
            $indent = "";
        }
        if ($default)
        {
            $default = "[<fg=yellow>" . $default . "</fg=yellow>]";
        } else
        {
            $default = "";
        }
        return "<fg=green>" . $indent . $question . " </fg=green>" . $default . "<fg=green>::</fg=green> ";
    }

}
