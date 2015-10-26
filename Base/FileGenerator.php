<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace CodeAge\EntityGeneratorBundle\Base;

/**
 * Description of FileGenerator
 *
 * @author Paweł
 */
class FileGenerator {

    private $data = array();
    private $path;
    private $arrayCollection = false;
    private $orm;

    public function __construct($data) {
        $this->data = $data;
        $bundlePath = str_replace('\\', '/', '\\src\\' . $data['namespace']);
        $path = getcwd() . $bundlePath;

        if (file_exists($path))
        {
            $this->path = $path;
        } else
        {
            $this->path = false;
        }
    }

    public function generateModel() {
        if (!$this->path)
        {
            return false;
        }
        $phpTag = "<?php" . PHP_EOL . PHP_EOL;
        $namespace = 'namespace ' . $this->data['namespace'] . '\\Model;' . PHP_EOL . PHP_EOL;
        $uses = 'use CodeAge\\EntityGeneratorBundle\\Entity\\AbstractEntity;' . PHP_EOL;
        $class = 'class ' . $this->data['class'] . ' extends AbstractEntity{' . PHP_EOL . PHP_EOL;

        $classProperties = "\tprotected \$id;" . PHP_EOL;
        $methods = $this->generateGet('id');
        $construct = "";
        foreach ($this->data['fields'] as $field)
        {
            $classProperties .= "\tprotected $" . $field['name'] . ";" . PHP_EOL;
            if ($field['type'] == 'entity' && ($field['attributes']['relation']['type'] == 'o2m' || $field['attributes']['relation']['type'] == 'm2m'))
            {
                $construct .= "\t\t\$this->" . $field['name'] . " = new ArrayCollection();" . PHP_EOL;
                if (!$this->arrayCollection)
                {
                    $uses .= "use Doctrine\\Common\\Collections\\ArrayCollection;" . PHP_EOL;
                    $this->arrayCollection = true;
                }
                $methods .= $this->generateFieldMethods($field, true);
            } else
            {
                $methods .= $this->generateFieldMethods($field);
            }
        }

        if ($construct != "")
        {
            $construct = "\tpublic function __construct(){" . PHP_EOL .
                    $construct .
                    "\t}" . PHP_EOL . PHP_EOL;
        }

        $file = $phpTag . $namespace . $uses . PHP_EOL . $class . $construct . $classProperties . PHP_EOL . PHP_EOL . $methods . PHP_EOL . "}";

        if (!file_exists($this->path . DIRECTORY_SEPARATOR . "Model"))
        {
            mkdir($this->path . DIRECTORY_SEPARATOR . "Model");
        }
        file_put_contents($this->path . DIRECTORY_SEPARATOR . "Model" . DIRECTORY_SEPARATOR . $this->data['class'] . ".php", $file);
        return true;
    }

    public function generateEntity() {
        if (!$this->path)
        {
            return false;
        }
        $phpTag = "<?php" . PHP_EOL . PHP_EOL;
        $namespace = 'namespace ' . $this->data['namespace'] . '\\Entity;' . PHP_EOL . PHP_EOL;
        $uses = 'use ' . $this->data['namespace'] . '\\Model\\' . $this->data['class'] . ' as Base;' . PHP_EOL . PHP_EOL;
        $class = 'class ' . $this->data['class'] . ' extends Base{' . PHP_EOL . PHP_EOL;

        $content = "\tconst EN = '" . $this->data['bundle'] . "';" . PHP_EOL .
                "\tconst ENN = '" . $this->data['namespace'] . "\\Entity\\" . $this->data['class'] . "';" . PHP_EOL;

        $file = $phpTag . $namespace . $uses . $class . $content . PHP_EOL . "}";

        if (!file_exists($this->path . DIRECTORY_SEPARATOR . "Entity"))
        {
            mkdir($this->path . DIRECTORY_SEPARATOR . "Entity");
        }
        file_put_contents($this->path . DIRECTORY_SEPARATOR . "Entity" . DIRECTORY_SEPARATOR . $this->data['class'] . ".php", $file);
        return true;
    }

    public function generateManager() {
        if (!$this->path)
        {
            return false;
        }
        $phpTag = "<?php" . PHP_EOL . PHP_EOL;
        $namespace = 'namespace ' . $this->data['namespace'] . '\\Manager;' . PHP_EOL . PHP_EOL;
        $uses = 'use CodeAge\\EntityGeneratorBundle\\Manager\\AbstractManager;' . PHP_EOL . PHP_EOL;
        $class = 'class ' . $this->data['class'] . 'Manager extends AbstractManager{' . PHP_EOL . PHP_EOL;

        $serviceName = strtolower(str_replace("\\", ".", $this->data['namespace']) . "." . $this->data['class']);

        $content = "\tconst SERVICE = '" . $serviceName . "';" . PHP_EOL . PHP_EOL;

        $file = $phpTag . $namespace . $uses . $class . $content . PHP_EOL . "}";

        if (!file_exists($this->path . DIRECTORY_SEPARATOR . "Manager"))
        {
            mkdir($this->path . DIRECTORY_SEPARATOR . "Manager");
        }
        file_put_contents($this->path . DIRECTORY_SEPARATOR . "Manager" . DIRECTORY_SEPARATOR . $this->data['class'] . "Manager.php", $file);
        return true;
    }

    private function generateFieldMethods($field, $arrayCollection = false) {
        $functions = "";
        $functions .= $this->generateSet($field['name']);
        $functions .= $this->generateGet($field['name']);
        if ($arrayCollection)
        {
            $functions .= $this->generateAdd($field['name']);
            $functions .= $this->generateRemove($field['name']);
        }
        return $functions;
    }

    private function generateSet($name) {
        $function = "\tpublic function set" . ucfirst($name) . "($" . $name . "){" . PHP_EOL .
                "\t\t\$this->" . $name . " = $" . $name . ";" . PHP_EOL .
                "\t\treturn \$this;" . PHP_EOL .
                "\t}" . PHP_EOL . PHP_EOL;
        return $function;
    }

    private function generateGet($name) {
        $function = "\tpublic function get" . ucfirst($name) . "(){" . PHP_EOL .
                "\t\treturn \$this->" . $name . ";" . PHP_EOL .
                "\t}" . PHP_EOL . PHP_EOL;
        return $function;
    }

    private function generateAdd($name) {
        $function = "\tpublic function add" . ucfirst($name) . "($" . $name . "){" . PHP_EOL .
                "\t\t\$this->" . $name . "->add($" . $name . ");" . PHP_EOL .
                "\t\treturn \$this;" . PHP_EOL .
                "\t}" . PHP_EOL . PHP_EOL;
        return $function;
    }

    private function generateRemove($name) {
        $function = "\tpublic function remove" . ucfirst($name) . "($" . $name . "){" . PHP_EOL .
                "\t\t\$this->" . $name . "->remove($" . $name . ");" . PHP_EOL .
                "\t\treturn \$this;" . PHP_EOL .
                "\t}" . PHP_EOL . PHP_EOL;
        return $function;
    }

    public function generateService() {
        $serviceXmlFile = $this->path . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "services.xml";
        if (file_exists($serviceXmlFile))
        {
            $services = simplexml_load_file($serviceXmlFile);
            if (!isset($services->parameters))
            {
                $parameters = $services->addChild('parameters');
            } else
            {
                $parameters = $services->parameters;
            }

            $managerParameter = $parameters->addChild('parameter', $this->data['namespace'] . "\\Manager\\" . $this->data['class'] . "Manager");
            $managerKey = strtolower(str_replace("\\", ".", $this->data['namespace']) . ".manager." . $this->data['class'] . ".class");
            $managerParameter->addAttribute('key', $managerKey);

            $entityParameter = $parameters->addChild('parameter', $this->data['namespace'] . "\\Entity\\" . $this->data['class']);
            $entityKey = strtolower(str_replace("\\", ".", $this->data['namespace']) . ".entity." . $this->data['class'] . ".class");
            $entityParameter->addAttribute('key', $entityKey);

            $serviceName = strtolower(str_replace("\\", ".", $this->data['namespace']) . "." . $this->data['class']);

            if (!isset($services->services))
            {
                $servicesNode = $services->addChild('services');
            } else
            {
                $servicesNode = $services->services;
            }

            $service = $servicesNode->addChild('service');

            $service->addAttribute('id', $serviceName);
            $service->addAttribute('class', "%" . $managerKey . "%");

            $argumentOne = $service->addChild('argument');
            $argumentOne->addAttribute('type', 'service');
            $argumentOne->addAttribute('id', 'doctrine.orm.default_entity_manager');

            $argumentTwo = $service->addChild('argument', "%" . $entityKey . "%");

            $services->asXml($serviceXmlFile);

            return true;
        }
        return false;
    }

    public function generateOrm() {
        $this->getBaseOrmXml();
        $fieldsNode = $this->orm->entity;
        foreach ($this->data['fields'] as $field)
        {
            switch ($field['type']) {
                case 'string':
                case 'integer':
                case 'boolean':
                case 'date':
                case 'datetime':
                case 'time':
                case 'text':
                case 'float':
                case 'decimal': $this->addSimpleField($field, $fieldsNode);
                    break;
                case 'entity': $this->addEntityField($field, $fieldsNode);
                    break;
            }
        }
        $doctrineCatalog = $this->path . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "doctrine";
        if (!file_exists($doctrineCatalog))
        {
            mkdir($doctrineCatalog);
        }
        $filename = $this->data['class'] . ".orm.xml";
        $this->orm->asXml($doctrineCatalog . DIRECTORY_SEPARATOR . $filename);
        return true;
    }

    private function addSimpleField($field, $fieldsNode) {
        $fieldNode = $fieldsNode->addChild('field');
        $fieldNode->addAttribute('name', $field['name']);
        $fieldNode->addAttribute('column', $field['name']);
        $fieldNode->addAttribute('type', $field['type']);
        foreach ($field['attributes'] as $name => $attribute)
        {
            if ($name == 'nullable' && $attribute)
            {
                $fieldNode->addAttribute($name, "true");
            }
        }
    }

    private function getBaseOrmXml() {
        $entity = $this->data['namespace'] . "\\Entity\\" . $this->data['class'];
        $stringXml = '<?xml version="1.0" encoding="utf-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping http://doctrine-project.org/schemas/orm/doctrine-mapping.xsd">
    <entity name="' . $entity . '">
        <id name="id" type="integer" column="id">
            <generator strategy="AUTO"/>
        </id>        
    </entity>
</doctrine-mapping>';
        $xml = simplexml_load_string($stringXml);
        $this->orm = $xml;
        return $this->orm;
    }

    private function addEntityField($field, $fieldsNode) {
        switch ($field['attributes']['relation']['type']) {
            case 'm2o':
                $fieldNode = $fieldsNode->addChild('many-to-one');
                $fieldNode->addAttribute('field', $field['name']);
                $fieldNode->addAttribute('target-entity', $field['attributes']['relation']['class']);
                break;
            case 'o2o': 
                $fieldNode = $fieldsNode->addChild('one-to-one');
                $fieldNode->addAttribute('field', $field['name']);
                $fieldNode->addAttribute('target-entity', $field['attributes']['relation']['class']);
                break;
            case 'm2m':
                $fieldNode = $fieldsNode->addChild('many-to-many');
                $fieldNode->addAttribute('field', $field['name']);
                $fieldNode->addAttribute('target-entity', $field['attributes']['relation']['class']);
                $joinedEntityName = preg_match('/[^\\\\]*?$/', $field['attributes']['relation']['class'], $matches);
                if($joinedEntityName){
                    $joinedEntityName = strtolower($matches[0]);
                }
                else{
                    $joinedEntityName = strtolower($field['attributes']['relation']['class']);
                }
                $joinTableName = strtolower($this->data['class'])."_".$joinedEntityName;
                $joinTable = $fieldNode->addChild('join-table');
                $joinTable->addAttribute('name',$joinTableName);
                
                $joinColumns = $joinTable->addChild('join-columns');
                $joinColumn = $joinColumns->addChild('join-column');
                $joinColumn->addAttribute('name',strtolower($this->data['class'])."_id");
                $joinColumn->addAttribute('referenced-column-name',"id");
                
                $inverseJoinColumns = $joinTable->addChild('inverse-join-columns');
                $invJoinColumn = $inverseJoinColumns->addChild('join-column');
                $invJoinColumn->addAttribute('name',$joinedEntityName."_id");
                $invJoinColumn->addAttribute('referenced-column-name',"id");
                
                break;
        }
        if ($field['attributes']['nullable'])
        {
            $fieldNode->addAttribute('nullable', "true");
        }
    }

}
