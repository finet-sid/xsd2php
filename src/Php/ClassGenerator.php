<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Php;

use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClass;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClassOf;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPProperty;
use Zend\Code\Generator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\PropertyTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;

class ClassGenerator
{

    private function handleBody(Generator\ClassGenerator $class, PHPClass $type)
    {
        $propertyMap = [];
        foreach ($type->getProperties() as $prop) {
            if ($prop->getName() !== '__value') {
                $propertyMap[$prop->getName()] = $this->handleProperty($class, $prop);
            }
        }

        $class->addConstant('PROPERTY_MAP', $propertyMap);

        $method = new MethodGenerator("getPropertyMap", []);
        $method->setStatic(true);
        if ($class->getExtendedClass()) {
            $methodBody = "\$properties = parent::getPropertyMap();" . PHP_EOL;
        } else {
            $methodBody = "\$properties = [];" . PHP_EOL;
        }
        $methodBody .= "return array_merge(\$properties, self::PROPERTY_MAP);" . PHP_EOL;
        $method->setBody($methodBody);
        $class->addMethodFromGenerator($method);

        if (count($type->getProperties()) === 1 && $type->hasProperty('__value')) {
            return false;
        }

        return true;
    }

    private function handleProperty(Generator\ClassGenerator $class, PHPProperty $prop)
    {
        $generatedProp = new PropertyGenerator($prop->getName());
        $generatedProp->setVisibility(PropertyGenerator::VISIBILITY_PUBLIC);

        $class->addPropertyFromGenerator($generatedProp);

        $docBlock = new DocBlockGenerator();
        $docBlock->setWordWrap(false);
        $generatedProp->setDocBlock($docBlock);

        if ($prop->getDoc()) {
            $docBlock->setLongDescription($prop->getDoc());
        }
        $tag = new PropertyTag($prop->getName(), 'mixed');

        $type = $prop->getType();

        if ($type && $type instanceof PHPClassOf) {
            $tt = $type->getArg()->getType();
            $tag->setTypes($tt->getPhpType() . "[]");
            if ($p = $tt->isSimpleType()) {
                if (($t = $p->getType())) {
                    $tag->setTypes($t->getPhpType() . "[]");
                }
            }
            $generatedProp->setDefaultValue($type->getArg()->getDefault());
        } elseif ($type) {

            if ($type->isNativeType()) {
                $tag->setTypes($type->getPhpType());
            } elseif (($p = $type->isSimpleType()) && ($t = $p->getType())) {
                $tag->setTypes($t->getPhpType());
            } else {
                $tag->setTypes($prop->getType()->getPhpType());
            }
        }

        $propertyTypes = $tag->getTypesAsString();

        $tag = new GenericTag('var', str_replace('@property ', '', $tag->generate()));
        $docBlock->setTag($tag);

        return $propertyTypes;
    }

    public function generate(PHPClass $type)
    {
        $class = new \Zend\Code\Generator\ClassGenerator();
        $docblock = new DocBlockGenerator("Class representing " . $type->getName());
        $docblock->setWordWrap(false);
        if ($type->getDoc()) {
            $docblock->setLongDescription($type->getDoc());
        }
        $class->setNamespaceName($type->getNamespace() ?: NULL);
        $class->setName($type->getName());
        $class->setDocblock($docblock);

        if ($extends = $type->getExtends()) {
            if ($p = $extends->isSimpleType()) {
                $this->handleProperty($class, $p);
            } else {

                $class->setExtendedClass($extends->getFullName());

                if ($extends->getNamespace() != $type->getNamespace()) {
                    if ($extends->getName() == $type->getName()) {
                        $class->addUse($type->getExtends()->getFullName(), $extends->getName() . "Base");
                    } else {
                        $class->addUse($extends->getFullName());
                    }
                }
            }
        }

        if ($this->handleBody($class, $type)) {
            return $class;
        }
    }
}
