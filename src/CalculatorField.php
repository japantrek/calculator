<?php
namespace nvbooster\Calculator;

/**
 * CalculatorField
 *
 * @author nvb <nvb@aproxima.ru>
 */
class CalculatorField
{
    protected $calculator;
    protected $name;
    protected $value;
    protected $base;

    protected $inputDependencies;
    protected $fieldDependencies;

    protected $calculateMethod;

    /**
     * @param string     $name
     * @param Calculator $calculator
     * @param array      $options
     */
    public function __construct($name, Calculator $calculator, $options = array())
    {
        $this->calculator = $calculator;
        $this->name = $name;

        $defaults = array(
            'dependsOnFields' => array(),
            'dependsOnInputs' => array(),
            'base' => Calculator::CURRENCY_RUB,
            'calculate' => array($this, 'sum'),
            'value' => 0
        );
        $options = array_merge($defaults, $options);

        $this->value = $options['value'];
        $this->base = $options['base'];
        $this->inputDependencies = $options['dependsOnInputs'];
        $this->fieldDependencies = $options['dependsOnFields'];
        $this->calculateMethod = $options['calculate'];
    }

    /**
     * recalculate
     */
    public function recalculate()
    {
        $inputParams = $this->getInputParams();
        $fieldParams = $this->getFieldParams();

        if (is_callable($this->calculateMethod)) {
            $value = call_user_func($this->calculateMethod, $inputParams, $fieldParams);

            if ($value != $this->value) {
                $this->value = $value;

                $this->calculator->triggerFieldChange($this->name);
            }
        }
    }

    /**
     * @return array
     */
    protected function getInputParams()
    {
        $inputParams = array();
        foreach ($this->inputDependencies as $input) {
            $inputParams[$input] = $this->calculator->getInputBase($input)
            ? $this->calculator->getInputValue($input, $this->base)
            : $this->calculator->getInputValue($input);
            array (
                $this->calculator->getInputValue($input),
                $this->calculator->getInputBase($input)
            );
        }

        return $inputParams;
    }

    /**
     * @return array
     */
    protected function getFieldParams()
    {
        $fieldParams = array();
        foreach ($this->fieldDependencies as $field) {
            $fieldParams[$field] = $this->calculator->getFieldValue($field, $this->base);
        }

        return $fieldParams;
    }

    /**
     * @return integer
     */
    public function getBase()
    {
        return $this->base;
    }

    /**
     * @param integer $base
     *
     * @return double
     */
    public function getValue($base = false)
    {
        if ($base && $base != $this->base) {
            return $this->calculator->convert($this->value, $this->base, $base);
        }

        return $this->value;
    }

    /**
     * @return array
     */
    public function getFieldDependencies()
    {
        return $this->fieldDependencies;
    }

    /**
     * @return array
     */
    public function getInputDependencies()
    {
        return $this->inputDependencies;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Default calculate method
     *
     * @param array $inputs
     * @param array $fields
     *
     * @return double
     */
    public function sum($inputs, $fields)
    {
        return array_sum($inputs) + array_sum($fields);
    }
}