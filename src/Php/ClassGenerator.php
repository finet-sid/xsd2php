<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Php;

use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClass;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClassOf;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPProperty;
use Zend\Code\Generator;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlock\Tag\PropertyTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
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

        if (count($type->getProperties()) === 1 && $type->hasProperty('__value')) {
            return false;
        }

        return true;
    }

    private function handleValueMethod(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class, $all = true)
    {
        $type = $prop->getType();

        $docblock = new DocBlockGenerator('Construct');
        $docblock->setWordWrap(false);
        $paramTag = new ParamTag("value");
        $paramTag->setTypes(($type ? $type->getPhpType() : "mixed"));

        $docblock->setTag($paramTag);

        $param = new ParameterGenerator("value");
        if ($type && !$type->isNativeType()) {
            $param->setType($type->getPhpType());
        }
        $method = new MethodGenerator("__construct", [
            $param
        ]);
        $method->setDocBlock($docblock);
        $method->setBody("\$this->value(\$value);");

        $generator->addMethodFromGenerator($method);

        $docblock = new DocBlockGenerator('Gets or sets the inner value');
        $docblock->setWordWrap(false);
        $paramTag = new ParamTag("value");
        if ($type && $type instanceof PHPClassOf) {
            $paramTag->setTypes($type->getArg()->getType()->getPhpType() . "[]");
        } elseif ($type) {
            $paramTag->setTypes($prop->getType()->getPhpType());
        }
        $docblock->setTag($paramTag);

        $returnTag = new ReturnTag("mixed");

        if ($type && $type instanceof PHPClassOf) {
            $returnTag->setTypes($type->getArg()->getType()->getPhpType() . "[]");
        } elseif ($type) {
            $returnTag->setTypes($type->getPhpType());
        }
        $docblock->setTag($returnTag);

        $param = new ParameterGenerator("value");
        $param->setDefaultValue(null);

        if ($type && !$type->isNativeType()) {
            $param->setType($type->getPhpType());
        }
        $method = new MethodGenerator("value", []);
        $method->setDocBlock($docblock);

        $methodBody = "if (\$args = func_get_args()) {" . PHP_EOL;
        $methodBody .= "    \$this->" . $prop->getName() . " = \$args[0];" . PHP_EOL;
        $methodBody .= "}" . PHP_EOL;
        $methodBody .= "return \$this->" . $prop->getName() . ";" . PHP_EOL;
        $method->setBody($methodBody);

        $generator->addMethodFromGenerator($method);

        $docblock = new DocBlockGenerator('Gets a string value');
        $docblock->setWordWrap(false);
        $docblock->setTag(new ReturnTag("string"));
        $method = new MethodGenerator("__toString");
        $method->setDocBlock($docblock);
        $method->setBody("return strval(\$this->" . $prop->getName() . ");");
        $generator->addMethodFromGenerator($method);
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
