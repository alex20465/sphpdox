<?php

namespace Sphpdox\Element;

use TokenReflection\ReflectionClass;

use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class element
 */
class ClassElement extends Element
{

    /**
     * @var boolean
     */
    public $skip_inherited_attr;

    /**
     * @var ReflectionClass
     */
    protected $reflection;

    /**
     * Constructor
     *
     * @param string $classname
     * @throws InvalidArgumentException
     */
    public function __construct(ReflectionClass $reflection)
    {
        parent::__construct($reflection);

        $this->skip_inherited_attr = true;
    }

    public function getPath()
    {
        return $this->reflection->getShortName() . '.rst';
    }

    /**
     * @param string $basedir
     * @param OutputInterface $output
     */
    public function build($basedir, OutputInterface $output)
    {
        $file = $basedir . DIRECTORY_SEPARATOR . $this->getPath();
        file_put_contents($file, $this->__toString());
    }

    /**
     * @see Sphpdox\Element.Element::__toString()
     */
    public function __toString()
    {
        $name = $this->reflection->getName();

        $label = str_replace('\\', '-', $name);
        $title = str_replace('\\', '\\\\', $name);
        //$title = $name;
        $string = ".. _$label:\n\n";
        $string .= str_repeat('-', strlen($title)) . "\n";
        $string .= $title . "\n";
        $string .= str_repeat('-', strlen($title)) . "\n\n";
        $string .= $this->getNamespaceElement();
        $string .= $this->getInheritanceTree();

        if ($this->reflection->isInterface()){
        	$string .= '.. php:interface:: ' ;
        } elseif ($this->reflection->isTrait()){
        	$string .= '.. php:trait:: ' ;
        } else {
        	$string .= '.. php:class:: ' ;
        }
        $string .= $this->reflection->getShortName();

        $parser = $this->getParser();

        if ($description = $parser->getDescription()) {
            $string .= "\n\n";
            $string .= $this->indent($description, 4);
        }

        foreach ($this->getSubElements() as $element) {
            $e = $element->__toString();
            if ($e) {
                $string .= "\n\n";
                $string .= $this->indent($e, 4);
            }
        }

        $string .= "\n\n";

        // Finally, fix some whitespace errors
        $string = preg_replace('/^\s+$/m', '', $string);
        $string = preg_replace('/ +$/m', '', $string);

        return $string;
    }

    protected function getSubElements()
    {
        $elements = array_merge(
            $this->getConstants(),
            $this->getProperties(),
            $this->getMethods()
        );

        return $elements;
    }

    protected function getConstants()
    {
        $const = $this->reflection->getConstantReflections();
        $classname = $this->reflection->getName();

        if( $this->skip_inherited_attr )
            $const = array_filter($const, function($prop) use ($classname){
                return $classname == $prop->getDeclaringClassName();
            });

        return array_map(function ($v) {
            return new ConstantElement($v);
        }, $const);
    }

    protected function getProperties()
    {
        $props = $this->reflection->getProperties();
        $classname = $this->reflection->getName();

        if( $this->skip_inherited_attr )
            $props = array_filter($props, function($prop) use ($classname){
                return $classname == $prop->getDeclaringClassName();
            });

        return array_map(function ($v) {
            return new PropertyElement($v);
        }, $props);
    }

    protected function getMethods()
    {
        $methods = $this->reflection->getMethods();
        $classname = $this->reflection->getName();

        if( $this->skip_inherited_attr )
            $methods = array_filter($methods, function($prop) use ($classname){
                return $classname == $prop->getDeclaringClassName();
            });

        return array_map(function ($v) {
            return new MethodElement($v);
        }, $methods);
    }

    public function getNamespaceElement()
    {
        return '.. php:namespace: '
            . str_replace('\\', '\\\\', $this->reflection->getNamespaceName())
            . "\n\n";
    }

    public function getInheritanceTree()
    {

        $parent_entries = [];

        $parents = $this->reflection->getParentClassNameList();
        $currentNamespace = $this->reflection->getNamespaceName();

        if( !empty($parents) ) {
            foreach ($parents as $key => $parent) {
                $string = ':ref:`';
                $string .= str_replace('\\', '-', $parent) . "`";
                $parent_entries[] = $string;
            }
        } else {
            return "";
        }

        $refs = join(' » ', $parent_entries);
        $title = "Inheritance:";

        return "$title\n     $refs\n\n";
    }
}