<?php

namespace DScribe\Form;

use DScribe\Core\Engine,
    Exception,
    Object;

/**
 * @todo allow as much external adding, changing and removing of elements and attributes as possible
 */
class Form extends Fieldset {

    /**
     * Name of form
     * @var string
     */
    protected $name;

    /**
     * Array of prepared elements, ready for rendering
     * @var \Object
     */
    private $prepared;

    public function __construct($name = 'form', array $attributes = array()) {
        $this->name = $name;
        if (!isset($attributes['name']))
            $attributes['name'] = $name;
        if (!isset($attributes['id']))
            $attributes['id'] = $name;

        parent::__construct($attributes);
    }

    /**
     * Fetches the name of the form
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Opens the form
     * @return \DScribe\Form\Forms
     */
    public function openTag() {
        return '<form ' . $this->parseAttributes($this->attributes) . '>' . "\n";
    }

    /**
     * Closes the form
     * @return \DScribe\Form\Forms
     */
    public function closeTag() {
        return '</form>' . "\n";
    }

    /**
     * Prepares the form for rendering
     * @param boolean $custom Indicates whether to prepare for a custom element renderer or internal
     * @return array Prepared elements
     */
    public function prepare($custom = false) {
        $this->prepared = new \Object();
        foreach ($this->elements as $element) {
            if ($element->type === 'fieldset')
                $this->prepFieldset($element, $custom);
            else
                $this->prepElement($element, $custom);
        }
        return $this->prepared;
    }

    /**
     * Prepares an element for rendering
     * @param \Object $element
     * @param boolean $return Indicates whether to return the element or add it to list of elements
     * @return Object|\DScribe\Form\Forms
     * @throws Exception
     */
    private function prepElement(\Object $element, $custom, $return = false) {
        if (!isset($this->msg[$element->name]))
            $this->msg[$element->name] = '';

        if (isset($element->options->label)) {
            $this->labels[$element->name] = array(
                'label' => $element->options->label,
                'attributes' => (isset($element->options->labelAttributes)) ? $this->parseAttributes($element->options->labelAttributes->toArray()) : '',
            );
        }

        $value = (isset($this->data[$element->name]) && !is_object($this->data[$element->name])) ?
                $this->data[$element->name] :
                ((isset($element->options->value)) ? $element->options->value : null);
        
        switch ($element->type) {
            case 'checkbox':
            case 'radio':
                if (isset($element->options->values) && !empty($element->options->values)) {
                    if (!is_array($element->options->values))
                        throw new Exception('Values in options for element "' . $element->name .
                        '" must an array of "element_label" => "element_value"');

                    $prepared = $element->options->values;

                    $name = ($element->type === 'radio') ? $element->name :
                            $element->name . '[]';
                    if (!$custom) {
                        $prepared = '';
                        foreach ($element->options->values as $label => $value) {
                            if (is_object($value) || is_array($value)) {
                                throw new Exception('Values for ' . $element->type . ' "' . $element->name . '" cannot be an object or an array');
                            }

                            $checked = (isset($this->data[$name]) || (isset($element->options->default) && $element->options->default == $value)) ?
                                    'checked="checked" ' : '';

                            $prepared .= '<label class="inner">' .
                                    '<input type="' . $element->type . '" ' .
                                    'name="' . $name . '" ' .
                                    'value="' . $value . '" ' . $checked .
                                    $this->parseAttributes($element->attributes->toArray()) .
                                    ' />' .
                                    $label . '</label>';
                        }
                        $prepared .= $this->msg[$name];
                    }
                }
                else {
                    $checked = ((isset($this->data[$element->name]) && $this->data[$element->name] != 0) ||
                            (!isset($this->data[$element->name]) && (isset($element->options->default) && $element->options->default))) ?
                            'checked="checked" ' : '';

                    $prepared = '<input type="' . $element->type . '" ' .
                            'name="' . $element->name . '" ' .
                            'value="1" ' . $checked .
                            $this->parseAttributes($element->attributes->toArray()) .
                            ' />' . $this->msg[$element->name];
                }
                break;
            case 'select':
                if ((isset($element->options->values) && !is_array($element->options->values)))
                    throw new Exception('Values in options for element "' . $element->name .
                    '" must an array of "element_value" => "element_label"');
                else if (isset($element->options->object) && (!isset($element->options->object->class)))
                    throw new Exception('Class not specified for select object in element "' . $element->name . '"');
                else if (isset($element->options->object) && isset($element->options->object->class) &&
                        !class_exists($element->options->object->class))
                    throw new Exception('Class "' . $element->options->object->class . '", as select object for element "' .
                    $element->name . '", not found');
                else if (isset($element->options->object)) {
                    $model = new $element->options->object->class;
                    $table = Engine::getDB()->table($model->getTableName(), $model);
                    $element->options->values = new Object();

                    $values = array();
                    $criteria = (isset($element->options->object->criteria)) ?
                            $element->options->object->criteria : array();

                    if (isset($element->options->object->sort)) {
                        if (is_string($element->options->object->sort)) {
                            $order = $table->orderBy($element->options->object->sort);
                        }
                        elseif (is_object($element->options->object->sort)) {
                            if (!isset($element->options->object->sort->column))
                                throw new Exception('Select element with object can only be sorted by columns. No column specified for element "' . $element->name . '"');
                            if (isset($element->options->object->sort->direction))
                                $order = $table->orderBy($element->options->object->sort->column, $element->options->object->sort->direction);
                            else
                                $order = $table->orderBy($element->options->object->sort->column);
                        } else {
                            throw new Exception('Sort value for select element with object can be either an array or a string. Invalid value for element for element "' . $element->name . '"');
                        }
                    }

                    $rows = (isset($order)) ? $order->select($criteria) : $table->select($criteria);
                    foreach ($rows as $row) {
                        if (!isset($element->options->object->labels) && !isset($element->options->object->values))
                            throw new \Exception('Options "labels" or "values" is required for select objects');

                        if (isset($element->options->object->labels)) {
                            if (method_exists($row, $element->options->object->labels))
                                $label = $row->{$element->options->object->labels}();
                            elseif (property_exists($row, $element->options->object->labels))
                                $label = $row->{$element->options->object->labels};
                            else
                                throw new \Exception('Class "' . $element->options->object->class . '" does not contain ' .
                                'property/method "' . $element->options->object->labels . '"');
                        }

                        if (isset($element->options->object->values)) {
                            if (method_exists($row, $element->options->object->values))
                                $value = $row->{$element->options->object->values}();
                            elseif (property_exists($row, $element->options->object->values))
                                $value = $row->{$element->options->object->values};
                            else
                                throw new \Exception('Class "' . $element->options->object->class . '" does not contain ' .
                                'property/method "' . $element->options->object->values . '"');

                            if (!isset($label)) {
                                $label = preg_replace('/[^A-Z0-9\s\'"-]/i', ' ', $value);
                            }
                        }

                        if (!isset($value)) {
                            $value = $label;
                        }

                        $values[$label] = $value;
                    }

                    $element->options->values->add($values);
                }

                $prepared = '<select name="' . $element->name . (isset($element->attributes->multiple) ? '[]' : '') . '" ' .
                        $this->parseAttributes($element->attributes->toArray()) . '>';

                if (isset($element->options->emptyValue)) {
                    $prepared .= '<option value="">' . $element->options->emptyValue . '</option>';
                }

                if (isset($element->options->values)) {
                    foreach ($element->options->values as $label => $value) {
                        $optGroup = false;
                        if (is_object($value)) {
                            $value = $value->toArray();
                            $optGroup = true;
                            $prepared .= '<optGroup';
                            if (!is_int($label)) {
                                $prepared .= ' label="' . $label . '"';
                            }
                            $prepared .= '>';
                        }
                        elseif (is_array($value)) {
                            $optGroup = false;
                            $optGroup = true;
                            $prepared .= '<optGroup';
                            if (!is_int($label)) {
                                $prepared .= ' label="' . $label . '"';
                            }
                            $prepared .= '>';
                        }
                        else {
                            $value = array($label => $value);
                        }
                        if (is_array($label) || is_object($label)) {
                            throw new Exception('Values of "option[values]" array for element "' .
                            $element->name . '" cannot be an array or an object');
                        }
                        foreach ($value as $lab => $val) {
                            $selected = '';
                            if ((isset($this->data[$element->name]) && ($this->data[$element->name] == $val || (is_array($this->data[$element->name]) && in_array($val, $this->data[$element->name])))) ||
                                    !isset($this->data[$element->name]) && isset($element->options->default)
                                    && $element->options->default == $val)
                                $selected = 'selected="selected"';

                            $prepared .= '<option value="' . $val . '" ' .
                                    $selected . '>' . $lab . '</option>';
                        }

                        if ($optGroup) {
                            $prepared .= '</optGroup>';
                        }
                    }
                }
                $prepared .= '</select>' . $this->msg[$element->name];

                break;
            case 'textarea':
            case 'button':
                $prepared = '<' . $element->type .
                        ' name="' . $element->name . '" ' .
                        $this->parseAttributes($element->attributes->toArray()) .
                        '>' . $value . '</' . $element->type . '>' . $this->msg[$element->name];
                break;
            default:
                $prepared = '<input type="' . $element->type . '" ' .
                        'name="' . $element->name . '" ' .
                        'value="' . $value . '" ' .
                        $this->parseAttributes($element->attributes->toArray()) .
                        ' />' . $this->msg[$element->name];
                break;
        }

        $ready[$element->name] = array(
            'id' => $element->attributes->id,
            'name' => $element->name,
            'type' => $element->type,
            'tag' => $prepared,
            'attributes' => $this->parseAttributes($element->attributes->toArray()),
        );

        if (in_array($element->type, array('checkbox', 'radio')) && isset($element->options->values)) {
            $ready[$element->name]['multiple'] = true;
            if (isset($this->data[$name]))
                $ready[$element->name]['default'] = $this->data[$name];
            elseif (isset($element->options->default))
                $ready[$element->name]['default'] = $element->options->default;
        }

        if ($return)
            return new Object($ready);

        $this->prepared->add($ready);
        return $this;
    }

    /**
     * Prepares a fieldset for rendering
     * @param Object $fieldset
     * @param string|null $name
     * @param boolean $return Indicates whether to return the fieldset or add it to list of elements
     * @return Object
     */
    private function prepFieldset(Object $fieldset, $custom, $name = null, $return = false) {
        $ready[$fieldset->name] = array(
            'id' => $fieldset->attributes->id,
            'name' => $fieldset->name,
            'type' => 'fieldset',
            'attributes' => $this->parseAttributes($fieldset->attributes->toArray()),
            'elements' => array(),
        );

        if (isset($fieldset->options->label)) {
            $ready[$fieldset->name]['label'] = array(
                'label' => $fieldset->options->label,
                'attributes' => (isset($fieldset->options->labelAttributes)) ? $this->parseAttributes($fieldset->options->labelAttributes->toArray()) : '',
            );
        }

        if ($this->model !== null) {
            $this->setData(new \Object(array(
                $fieldset->name => $fieldset->options->value->loadModel($fieldset->name, $this->model)
            )));
        }

        foreach ($fieldset->options->value->getElements() as $element) {
            $element->name = ($name === null) ? $fieldset->name . '[' . $element->name . ']' :
                    $name . '[' . $element->name . ']';

            if ($element->type !== 'fieldset') {
                if ($element->dId)
                    $element->attributes->id = $element->name;
                $ready[$fieldset->name]['elements'] = array_merge($ready[$fieldset->name]['elements'], $this->prepElement($element, $custom, true)->toArray());
            }
            else {
                $ready[$fieldset->name]['elements'] = array_merge($ready[$fieldset->name]['elements'], $this->prepFieldset($element, $custom, $element->name, true)->toArray());
            }
        }

        if ($return)
            return new Object($ready);

        $this->prepared->add($ready);
    }

    /**
     * Returns array of filters for elements
     * @return array
     */
    public function filters() {
        return parent::filters();
    }

    /**
     * Renders the form to the browser
     * @return \DScribe\Form\Forms
     */
    public function render() {
        $this->prepare();
        ob_start();
        echo $this->openTag();
        foreach ($this->prepared->toArray() as $name => $element) {
            if ($element->type !== 'fieldset')
                $this->renderElement($name, $element);
            else {
                $this->renderFieldset($element);
            }
        }
        echo $this->closeTag();

        return ob_get_clean();
    }

    /**
     * Renders a fieldset to the browser
     * @param Object $fieldset
     */
    private function renderFieldset(Object $fieldset, $pre = null) {
        echo $pre . "\t" . '<fieldset id="' . $fieldset->id . '" ' . $fieldset->attributes . '>';
        if (isset($fieldset->label)) {
            echo $pre . "\t\t" . '<legend ' . $fieldset->label->attributes . '>' .
            $fieldset->label->label . '</legend>';
        }

        foreach ($fieldset->elements->toArray() as $element) {
            if ($element->type !== 'fieldset')
                $this->renderElement($element->name, $element, "\t");
            else
                $this->renderFieldset($element, "\t");
        }
        echo $pre . "\t" . '</fieldset>';
    }

    /**
     * Renders an element to the browser
     * @param string $name
     * @param Object $element
     */
    private function renderElement($name, Object $element, $pre = null) {
        if ($element->type !== 'hidden') {
            echo $pre . "\t" . '<div class="element-group ' . $element->type . ((!empty($this->msg[$name]) ? ' form-error' : '')) . '">' . "\n";
            if (isset($this->labels[$name]) && (!in_array($element->type, array('checkbox', 'radio')) ||
                    isset($element->multiple))) {
                echo $pre . "\t\t" . '<label for"' . $element->id . '" ' . $this->labels[$name]['attributes'] . '>'
                . $this->labels[$name]['label'] .
                ((!empty($this->labels[$name]['attributes']) && stristr($this->labels[$name]['attributes'], 'title="')) ?
                        ' <abbr class="info">(!)</abbr>' : '') .
                '</label>' . "\n";
            }
            elseif (in_array($element->type, array('checkbox', 'radio'))) {
                echo $pre . "\t\t" . '<label ' . $this->labels[$name]['attributes'] . '>' . "\n\t";
            }

            if (!in_array($element->type, array('checkbox, radio')))
                echo $pre . "\t\t";
        }
        echo $element->tag;
        if ($element->type !== 'hidden') {
            if (!in_array($element->type, array('checkbox, radio')))
                echo "\n";

            if (isset($this->labels[$name]) && (in_array($element->type, array('checkbox', 'radio')) &&
                    !isset($element->multiple))) {
                echo $pre . " \t\t\t" . $this->labels[$name]['label'] .
                ((!empty($this->labels[$name]['attributes']) && stristr($this->labels[$name]['attributes'], 'title="')) ?
                        ' <abbr class="info">(!)</abbr>' : '') .
                "\n\t\t</label>\n";
            }

            echo $pre . "\t" . '</div>' . "\n";
        }
    }

    /**
     * Removes the data in the form
     * @return \DScribe\Form\Form
     */
    public function reset() {
        $this->data = array();
        return $this;
    }

}
