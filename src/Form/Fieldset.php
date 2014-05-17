<?php

namespace DScribe\Form;

use DScribe\Core\IModel,
    Exception,
    Object;

/**
 * Fieldset class
 *
 * @author Ezra Obiwale
 * @todo Create individual element, validator, and filter classes for easier extensions
 */
class Fieldset extends AValidator {

    /**
     * Attribute array
     * @var array
     */
    protected $attributes;

    /**
     * @var \DScribe\Core\IModel
     */
    protected $model;

    /**
     * Array of labels for elements
     * @var array
     */
    protected $labels;

    /**
     * Class constructor
     * @param string $name Name of fieldset
     * @param array $attributes
     */
    public function __construct(array $attributes = array()) {
        parent::__construct();
        $this->attributes = $this->noFilter = array();
        $this->setAttributes($attributes);
    }

    /**
     * Sets attributes for fieldset
     * @param array $attributes
     * @return \DScribe\Form\Fieldset
     */
    public function setAttributes(array $attributes) {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Sets a fieldset attribute
     * @param string $attr
     * @param mixed $val
     * @return \DScribe\Form\Fieldset
     */
    final public function setAttribute($attr, $val) {
        $this->attributes[$attr] = $val;
        return $this;
    }

    /**
     * Fetches an attribute of the fieldset
     * @param string $attr
     * @return mixed
     */
    final public function getAttribute($attr) {
        if (is_array($this->attributes) && array_key_exists($attr, $this->attributes)) {
            return $this->attributes[$attr];
        }
    }

    /**
     * Fetches the attributes of the fieldset
     * @param boolean $parsed
     * @return string|array
     */
    final public function getAttributes($parsed = false) {
        if (!is_array($this->attributes)) {
            $this->attributes = array();
        }
        return ($parsed) ? $this->parseAttributes($this->attributes) : $this->attributes;
    }

    /**
     * Parses attributes for rendering
     * @param array $attrs
     * @return string
     */
    final protected function parseAttributes(array $attrs) {
        $return = '';
        foreach ($attrs as $attr => $val) {
            $return .= \Util::camelToHyphen($attr) . '="' . $val . '" ';
        }
        return $return;
    }

    /**
     * Maps the form to a model
     * @param IModel $model
     * @return \DScribe\Form\Form
     */
    final public function setModel(IModel $model) {
        $this->model = $model;
        $this->setData($model->toArray(false, true));
        return $this;
    }

    /**
     * Fetches the populated model
     * @return IModel
     */
    public function getModel() {
        if (!$this->model)
            return null;

        $data = $this->data;
        if ($this->isValid() && $this->model) {
            foreach ($this->fieldsets as $name) {
                if (!isset($this->elements[$name]))
                    continue;

                $fieldset = $this->elements[$name]->options->value;
                $fieldsetModel = $fieldset->getModel();
                $data[$name] = $fieldsetModel ? $fieldsetModel : $fieldset->getData();
            }
            $this->model->populate($data);
        }

        return $this->model;
    }

    /**
     * Fetches the class name of the form model
     * @return null|string
     */
    final public function getModelClass() {
        if ($this->model === null)
            return null;

        return get_class($this->model);
    }

    /**
     * Loads the model of the fieldset from the db
     * @param string $fieldsetName
     * @param \DScribe\Core\IModel $model
     * @return \Object
     */
    final public function loadModel($fieldsetName, IModel &$model) {
        $return = array();
        if ($this->model !== null) {
            if ($result = $model->$fieldsetName()) {
                if ($result->count()) {
                    $return = $result->first()->toArray();
                }
            }
        }

        if (!empty($this->fieldsets)) {
            foreach ($this->fieldsets as $name) {
                if (!isset($this->elements[$name]))
                    continue;

                $fieldset = $this->elements[$name]->options->value;
                $return[$fieldset->name] = $fieldset->loadModel($fieldset->name, $this->model);
            }
        }
        return $return;
    }

    /**
     * Fetches the label of an
     * @param string $elementName
     * @return string|null
     */
    public function getLabel($elementName) {
        if (isset($this->labels[$elementName])) {
            return new Object($this->labels[$elementName]);
        }
    }

    /**
     * Adds an element to the fieldset
     * @param array $element
     * @return \DScribe\Form\Fieldset
     * @throws Exception
     * @todo Check validity immediately to allow easy exception trace
     * @todo Create individual element class with it's own prepare method for output
     */
    public function add(array $element) {
        if (!is_array($element))
            throw new Exception('Form elements must be an array');

        $element = new Object($element, true, 'values');

        if (empty($element->name))
            throw new Exception('Form elements must have a name');
        if (empty($element->type))
            throw new Exception('Form element "' . $element->name . '" does not have a type');

        $element->type = strtolower($element->type);

        if (empty($element->options))
            $element->options = new Object();

        if (!is_object($element->options) || (is_object($element->options) &&
                get_class($element) !== 'Object'))
            throw new Exception('Form element options of "' . $element->name . '" must be an array');
        if ($element->type === 'fieldset' && !isset($element->options->value))
            throw new Exception('Form fieldset element "' . $element->name .
            '" of must have a value of type "DScribe\Form\Fieldset"');
        elseif ($element->type === 'fieldset' && (!is_object($element->options->value) ||
                (is_object($element->options->value) && !in_array('DScribe\Form\Fieldset', class_parents($element->options->value))))) {
            throw new Exception('Form element "' . $element->name .
            '" must have a value of object "DScribe\Form\Fieldset"');
        }

        if (!empty($element->attributes) && (!is_object($element->attributes) ||
                (is_object($element->attributes) && get_class($element) !== 'Object')))
            throw new Exception('Form element attributes of "' . $element->name . '" must be an array');

        if (empty($element->attributes))
            $element->attributes = new Object();

        if (empty($element->attributes->id)) {
            $element->attributes->id = $element->name;
            $element->dId = true;
        }
        else
            $element->dId = false;

        if (!empty($element->attributes->value)) {
            if (!isset($element->options->value))
                $element->options->value = $element->attributes->value;
            unset($element->attributes->value);
        }

        if (in_array($element->type, array('checkbox', 'radio'))) {
            $this->booleans[$element->name] = $element->name;

            if (!isset($element->options->value) || (isset($element->options->value) && $element->options->value != 0))
                $element->options->value = '1';
        }

        if (empty($element->options->value) && empty($element->options->values))
            $element->options->value = null;

        if ($element->type === 'fieldset') {
            $this->fieldsets[] = $element->name;
        }

        $this->elements[$element->name] = $element;

        if (isset($element->filters)) {
            $this->addFilters($element->name, $element->filters->toArray(true));
        }
        return $this;
    }

    /**
     * Fetches all elements in the fieldset
     * @return array
     */
    final public function getElements() {
        return $this->elements;
    }

    /**
     * Fetches an element
     * @param string $name
     * @return \Object
     */
    final public function get($name) {
        return @$this->elements[$name];
    }

    /**
     * Array of filters for the elements
     * @return array
     */
    public function getFilters() {
        return array();
    }

    /**
     * Signifies a filter for an element should be ignored when validating
     * @param string $elementName
     * @return \DScribe\Form\Fieldset
     */
    final public function ignoreFilter($elementName) {
        $this->noFilter[] = $elementName;
        return $this;
    }

    /**
     * Removes an element
     * @param string $elementName
     * @return \DScribe\Form\Fieldset
     */
    final public function remove($elementName) {
        if (isset($this->elements[$elementName])) {
            unset($this->elements[$elementName]);
            unset($this->data[$elementName]);
            unset($this->booleans[$elementName]);
            unset($this->fieldsets[$elementName]);
            $this->ignoreFilter($elementName);
        }

        return $this;
    }

    /**
     * Sets data to validate
     * @param \Object | array $data
     * @return \DScribe\Form\Fieldset
     * @throws Exception
     */
    final public function setData($data) {
        if (is_object($data) && !is_a($data, 'Object'))
            throw new \Exception('Data must be either an array or an object that extends \Object');

        $this->data = (is_array($data)) ? $data : $data->toArray();
        if (is_null($this->booleans))
            $this->booleans = array();

        foreach ($this->booleans as $name) {
            if (!isset($this->data[$name])) {
                $this->data[$name] = '0';
            }
        }
        $this->valid = null;

        return $this;
    }

    /**
     * Fetches the filtered data
     * @return Object
     */
    final public function getData($toArray = false) {
        if ($toArray)
            return ($this->data) ? $this->data : array();
        else
            return ($this->data) ? new \Object($this->data) : new \Object();
    }

}
