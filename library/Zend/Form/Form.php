<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Form
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Form;

use Traversable;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterInterface;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\InputFilter\InputProviderInterface;

/**
 * @category   Zend
 * @package    Zend_Form
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Form extends BaseForm implements FormFactoryAwareInterface
{
    /**
     * @var Factory
     */
    protected $factory;

    /**
     * Whether or not to automatically scan for input filter defaults on
     * attached fieldsets and elements
     *
     * @var bool
     */
    protected $useInputFilterDefaults = true;

    /**
     * Compose a form factory to use when calling add() with a non-element/fieldset
     *
     * @param  Factory $factory
     * @return Form
     */
    public function setFormFactory(Factory $factory)
    {
        $this->factory = $factory;
        return $this;
    }

    /**
     * Retrieve composed form factory
     *
     * Lazy-loads one if none present.
     *
     * @return Factory
     */
    public function getFormFactory()
    {
        if (null === $this->factory) {
            $this->setFormFactory(new Factory());
        }
        return $this->factory;
    }

    /**
     * Set flag indicating whether or not to scan elements and fieldsets for defaults
     *
     * @param  bool $useInputFilterDefaults
     * @return Form
     */
    public function setUseInputFilterDefaults($useInputFilterDefaults)
    {
        $this->useInputFilterDefaults = (bool) $useInputFilterDefaults;
        return $this;
    }

    /**
     * Should we use input filter defaults from elements and fieldsets?
     *
     * @return bool
     */
    public function useInputFilterDefaults()
    {
        return $this->useInputFilterDefaults;
    }

    /**
     * Add an element or fieldset
     *
     * If $elementOrFieldset is an array or Traversable, passes the argument on
     * to the composed factory to create the object before attaching it.
     *
     * $flags could contain metadata such as the alias under which to register
     * the element or fieldset, order in which to prioritize it, etc.
     *
     * @param  array|Traversable|ElementInterface $elementOrFieldset
     * @param  array $flags
     * @return Form
     */
    public function add($elementOrFieldset, array $flags = array())
    {
        if (is_array($elementOrFieldset)
            || ($elementOrFieldset instanceof Traversable && !$elementOrFieldset instanceof ElementInterface)
        ) {
            $factory = $this->getFormFactory();
            $elementOrFieldset = $factory->create($elementOrFieldset);
        }
        return parent::add($elementOrFieldset, $flags);
    }

    /**
     * Ensures state is ready for use
     *
     * Marshalls the input filter, to ensure validation error messages are
     * available, and prepares any elements and/or fieldsets that require
     * preparation.
     *
     * @return void
     */
    public function prepare()
    {
        $this->getInputFilter();

        foreach ($this->getIterator() as $elementOrFieldset) {
            if ($elementOrFieldset instanceof ElementPrepareAwareInterface) {
                $this->prepareElement($elementOrFieldset);
                continue;
            }
            if ($elementOrFieldset instanceof FieldsetInterface) {
                $this->prepareFieldset($elementOrFieldset);
                continue;
            }
        }
    }

    /**
     * Prepare a form element
     *
     * @param  ElementPrepareAwareInterface $element
     * @return void
     */
    protected function prepareElement(ElementPrepareAwareInterface $element)
    {
        $element->prepare($this);
    }

    /**
     * Recursively prepare a fieldset
     * 
     * @param  FieldsetInterface $fieldset 
     * @return void
     */
    protected function prepareFieldset(FieldsetInterface $fieldset)
    {
        if (method_exists($fieldset, 'prepare')) {
            $fieldset->prepare();
            return;
        }

        foreach ($fieldset->getIterator() as $elementOrFieldset) {
            if ($elementOrFieldset instanceof ElementPrepareAwareInterface) {
                $this->prepareElement($elementOrFieldset);
                continue;
            }
            if ($elementOrFieldset instanceof FieldsetInterface) {
                $this->prepareFieldset($elementOrFieldset);
                continue;
            }
        }
    }

    /**
     * Retrieve input filter used by this form.
     *
     * Attaches defaults from attached elements, if no corresponding input
     * exists for the given element in the input filter.
     *
     * @return InputFilterInterface
     */
    public function getInputFilter()
    {
        $filter = parent::getInputFilter();
        if ($filter instanceof InputFilterInterface && $this->useInputFilterDefaults()) {
            $this->attachInputFilterDefaults($filter, $this);
        }
        return $this->filter;
    }

    /**
     * Attach defaults provided by the elements to the input filter
     *
     * @param  InputFilterInterface $inputFilter
     * @param  FieldsetInterface $fieldset Fieldset to traverse when looking for default inputs
     * @return void
     */
    public function attachInputFilterDefaults(InputFilterInterface $inputFilter, FieldsetInterface $fieldset)
    {
        $formFactory  = $this->getFormFactory();
        $inputFactory = $formFactory->getInputFilterFactory();
        foreach ($fieldset->getElements() as $element) {
            if (!$element instanceof InputProviderInterface) {
                // only interested in the element if it provides input information
                continue;
            }

            $name = $element->getName();
            if ($inputFilter->has($name)) {
                // if we already have an input by this name, use it
                continue;
            }

            // Create an input based on the specification returned from the element
            $spec  = $element->getInputSpecification();
            $input = $inputFactory->createInput($spec);
            $inputFilter->add($input, $name);
        }

        foreach ($fieldset->getFieldsets() as $fieldset) {
            $name = $fieldset->getName();
            if (!$fieldset instanceof InputFilterProviderInterface) {
                if (!$inputFilter->has($name)) {
                    // Add a new empty input filter if it does not exist, so that elements of nested fieldsets can be
                    // recursively added
                    $inputFilter->add(new InputFilter(), $name);
                }

                $fieldsetFilter = $inputFilter->get($name);

                if (!$fieldsetFilter instanceof InputFilterInterface) {
                    // Input attached for fieldset, not input filter; nothing more to do.
                    continue;
                }

                // Traverse the elements of the fieldset, and attach any
                // defaults to the fieldset's input filter
                $this->attachInputFilterDefaults($fieldsetFilter, $fieldset);
                continue;
            }

            if ($inputFilter->has($name)) {
                // if we already have an input/filter by this name, use it
                continue;
            }

            // Create an inputfilter based on the specification returned from the fieldset
            $spec   = $fieldset->getInputFilterSpecification();
            $filter = $inputFactory->createInputFilter($spec);
            $inputFilter->add($filter, $name);

            // Recursively attach sub filters
            $this->attachInputFilterDefaults($filter, $fieldset);
        }
    }
}
