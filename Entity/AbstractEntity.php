<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace CodeAge\EntityGeneratorBundle\Entity;

/**
 * Description of AbstractEntity
 *
 * @author Paweł
 */
class AbstractEntity {

    public static function getClassNamespace($entity) {
        return 'ObjectName';
    }

}
