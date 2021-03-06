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
 * @subpackage View
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Form\View\Helper;

use Traversable;
use Zend\Loader\Pluggable;
use Zend\Form\ElementInterface;
use Zend\Form\Exception;

/**
 * @category   Zend
 * @package    Zend_Form
 * @subpackage View
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class FormMultiCheckbox extends FormInput
{
    const LABEL_APPEND  = 'append';
    const LABEL_PREPEND = 'prepend';

    /**
     * @var boolean
     */
    protected $useHiddenElement = false;

    /**
     * @var string
     */
    protected $uncheckedValue = '';

    /**
     * @var FormInput
     */
    protected $inputHelper;

    /**
     * @var FormLabel
     */
    protected $labelHelper;

    /**
     * @var string
     */
    protected $labelPosition = self::LABEL_APPEND;

    /**
     * @var array
     */
    protected $labelAttributes;

    /**
     * @var string
     */
    protected $separator = '';

    /**
     * Set value for labelPosition
     *
     * @param  mixed labelPosition
     * @return $this
     */
    public function setLabelPosition($labelPosition)
    {
        $labelPosition = strtolower($labelPosition);
        if (!in_array($labelPosition, array(self::LABEL_APPEND, self::LABEL_PREPEND))) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects either %s::LABEL_APPEND or %s::LABEL_PREPEND; received "%s"',
                __METHOD__,
                __CLASS__,
                __CLASS__,
                (string) $labelPosition
            ));
        }
        $this->labelPosition = $labelPosition;
        return $this;
    }

    /**
     * Get position of label
     *
     * @return string
     */
    public function getLabelPosition()
    {
        return $this->labelPosition;
    }

    /**
     * Set separator string for checkbox elements
     *
     * @param  string $separator
     * @return FormMultiCheckbox
     */
    public function setSeparator($separator)
    {
        $this->separator = (string) $separator;
        return $this;
    }

    /**
     * Get separator for checkbox elements
     *
     * @return string
     */
    public function getSeparator()
    {
        return $this->separator;
    }

    /**
     * Sets the attributes applied to option label.
     *
     * @param  array|null $attributes
     * @return FormMultiCheckbox
     */
    public function setLabelAttributes($attributes)
    {
        $this->labelAttributes = $attributes;
        return $this;
    }

    /**
     * Returns the attributes applied to each option label.
     *
     * @return array|null
     */
    public function getLabelAttributes()
    {
        return $this->labelAttributes;
    }

    /**
     * Returns the option for prefixing the element with a hidden element
     * for the unset value.
     *
     * @return boolean
     */
    public function getUseHiddenElement()
    {
        return $this->useHiddenElement;
    }

    /**
     * Sets the option for prefixing the element with a hidden element
     * for the unset value.
     *
     * @param  boolean $useHiddenElement
     * @return FormMultiCheckbox
     */
    public function setUseHiddenElement($useHiddenElement)
    {
        $this->useHiddenElement = (bool) $useHiddenElement;
        return $this;
    }

    /**
     * Returns the unchecked value used when "UseHiddenElement" is turned on.
     *
     * @return string
     */
    public function getUncheckedValue()
    {
        return $this->uncheckedValue;
    }

    /**
     * Sets the unchecked value used when "UseHiddenElement" is turned on.
     *
     * @param  boolean $value
     * @return FormMultiCheckbox
     */
    public function setUncheckedValue($value)
    {
        $this->uncheckedValue = $value;
        return $this;
    }

    /**
     * Render a form <input> element from the provided $element
     *
     * @param  ElementInterface $element
     * @return string
     */
    public function render(ElementInterface $element)
    {
        $name = static::getName($element);
        if (empty($name)) {
            throw new Exception\DomainException(sprintf(
                '%s requires that the element has an assigned name; none discovered',
                __METHOD__
            ));
        }

        $attributes = $element->getAttributes();

        if (!isset($attributes['options'])
            || (!is_array($attributes['options']) && !$attributes['options'] instanceof Traversable)
        ) {
            throw new Exception\DomainException(sprintf(
                '%s requires that the element has an array or Traversable "options" attribute; none found',
                __METHOD__
            ));
        }

        $options = $attributes['options'];
        unset($attributes['options']);

        $attributes['name'] = $name;
        $attributes['type'] = $this->getInputType();

        $selectedOptions = array();
        if (isset($attributes['value'])) {
            $selectedOptions = (array) $attributes['value'];
            unset($attributes['value']);
        }

        $rendered = $this->renderOptions($options, $selectedOptions, $attributes);

        // Render hidden element
        $useHiddenElement = isset($attributes['useHiddenElement'])
            ? (bool) $attributes['useHiddenElement']
            : $this->useHiddenElement;

        if ($useHiddenElement) {
            $rendered = $this->renderHiddenElement($element, $attributes) . $rendered;
        }

        return $rendered;
    }

    protected function renderOptions(array $options, array $selectedOptions, array $attributes)
    {
        if (isset($attributes['labelAttributes'])) {
            $globalLabelAttributes = $attributes['labelAttributes'];
            unset($attributes['labelAttributes']);
        } else {
            $globalLabelAttributes = $this->labelAttributes;
        }

        $inputHelper    = $this->getInputHelper();
        $escapeHelper   = $this->getEscapeHelper();
        $labelHelper    = $this->getLabelHelper();
        $labelClose     = $labelHelper->closeTag();
        $labelPosition  = $this->getLabelPosition();
        $closingBracket = $this->getInlineClosingBracket();

        $combinedMarkup = array();
        $count          = 0;

        foreach ($options as $key => $optionSpec) {
            $count++;
            if ($count > 1 && array_key_exists('id', $attributes)) {
                unset($attributes['id']);
            }

            $value           = '';
            $label           = $key;
            $selected        = false;
            $disabled        = false;
            $inputAttributes = $attributes;
            $labelAttributes = $globalLabelAttributes;

            if (is_string($optionSpec) || is_numeric($optionSpec) || is_bool($optionSpec)) {
                $optionSpec = array('value' => (string) $optionSpec);
            }

            if (isset($optionSpec['value'])) {
                $value = $optionSpec['value'];
            }
            if (isset($optionSpec['label'])) {
                $label = $optionSpec['label'];
            }
            if (isset($optionSpec['selected'])) {
                $selected = $optionSpec['selected'];
            }
            if (isset($optionSpec['disabled'])) {
                $disabled = $optionSpec['disabled'];
            }
            if (isset($optionSpec['labelAttributes'])) {
                $labelAttributes = (isset($labelAttributes))
                    ? array_merge($labelAttributes, $optionSpec['labelAttributes'])
                    : $optionSpec['labelAttributes'];
            }
            if (isset($optionSpec['attributes'])) {
                $inputAttributes = array_merge($inputAttributes, $optionSpec['attributes']);
            }

            if (in_array($value, $selectedOptions, true)) {
                $selected = true;
            }

            $inputAttributes['value']    = $value;
            $inputAttributes['checked']  = $selected;
            $inputAttributes['disabled'] = $disabled;

            $input = sprintf(
                '<input %s%s',
                $this->createAttributesString($inputAttributes),
                $closingBracket
            );

            $label     = $escapeHelper($label);
            $labelOpen = $labelHelper->openTag($labelAttributes);
            $template  = $labelOpen . '%s%s' . $labelClose;
            switch ($labelPosition) {
                case self::LABEL_PREPEND:
                    $markup = sprintf($template, $label, $input);
                    break;
                case self::LABEL_APPEND:
                default:
                    $markup = sprintf($template, $input, $label);
                    break;
            }

            $combinedMarkup[] = $markup;
        }

        return implode($this->getSeparator(), $combinedMarkup);
    }

    /**
     * Render a hidden element for empty/unchecked value
     *
     * @param  ElementInterface $element
     * @param  array $attributes
     * @return string
     */
    protected function renderHiddenElement(ElementInterface $element, array $attributes)
    {
        $closingBracket = $this->getInlineClosingBracket();

        $uncheckedValue = isset($attributes['uncheckedValue'])
                ? $attributes['uncheckedValue']
                : $this->uncheckedValue;

        $hiddenAttributes = array(
            'name'  => $element->getName(),
            'value' => $uncheckedValue,
        );

        return sprintf(
            '<input type="hidden" %s%s',
            $this->createAttributesString($hiddenAttributes),
            $closingBracket
        );
    }

    /**
     * Invoke helper as functor
     *
     * Proxies to {@link render()}.
     *
     * @param  ElementInterface|null $element
     * @return string
     */
    public function __invoke(ElementInterface $element = null)
    {
        if (!$element) {
            return $this;
        }

        return $this->render($element);
    }

    /**
     * Return input type
     *
     * @return string
     */
    protected function getInputType()
    {
        return 'checkbox';
    }

    /**
     * Retrieve the FormInput helper
     *
     * @return FormInput
     */
    protected function getInputHelper()
    {
        if ($this->inputHelper) {
            return $this->inputHelper;
        }

        if (method_exists($this->view, 'plugin')) {
            $this->inputHelper = $this->view->plugin('form_input');
        }

        if (!$this->inputHelper instanceof FormInput) {
            $this->inputHelper = new FormInput();
        }

        return $this->inputHelper;
    }

    /**
     * Retrieve the FormLabel helper
     *
     * @return FormLabel
     */
    protected function getLabelHelper()
    {
        if ($this->labelHelper) {
            return $this->labelHelper;
        }

        if (method_exists($this->view, 'plugin')) {
            $this->labelHelper = $this->view->plugin('form_label');
        }

        if (!$this->labelHelper instanceof FormLabel) {
            $this->labelHelper = new FormLabel();
        }

        return $this->labelHelper;
    }

    /**
     * Get element name
     *
     * @param  ElementInterface $element
     * @return string
     */
    protected static function getName(ElementInterface $element)
    {
        return $element->getName() . '[]';
    }
}
