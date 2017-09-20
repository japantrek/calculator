<?php
namespace nvbooster\Calculator;

/**
 * CalculatorInput
 *
 * @author nvb <nvb@aproxima.ru>
 */
class CalculatorInput
{
    private $calculator;
    private $name;
    private $values = false;
    private $base = 0;
    private $value;
    private $label;

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
            'value' => false,
            'values' => false,
            'base' => Calculator::CURRENCY_NONE,
            'label' => ucfirst($name)
        );

        $options = array_merge($defaults, $options);

        if ($options['values'] && count($options['values'])) {
            $this->values = array();
            foreach ($options['values'] as $value => $label) {
                $this->values[(string) $value] = $label;
            }
        }

        $this->base = $options['base'];

        if ($options['value']) {
            $this->value = $options['value'];
        } elseif (is_array($this->values)) {
            reset($this->values);
            $this->value = key($this->values);
        }

        if (is_callable($options['label'])) {
            $this->label = $options['label'];
        } else {
            $this->label = (string) $options['label'];
        }
    }

    /**
     * @return integer
     */
    public function getBase()
    {
        return $this->base;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param integer $base
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
    public function getValue($base = false)
    {

        if ($base) {
            if (!$this->base) {
                throw new \InvalidArgumentException('This input is non money type');
            }

            if ($base != $this->base) {
                return $this->calculator->convert($this->value, $this->base, $base);
            }
        }

        return $this->value;
    }

    /**
     * @param mixed   $value
     * @param integer $base
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function setValue($value, $base = false)
    {
        if ($base) {
            if (!$this->base) {
                throw new \InvalidArgumentException('This input is non money type');
            }

            if ($base != $this->base) {
                $value = $this->calculator->convert($value, $base, $this->base);
            }
        }

        $this->value = $value;

        if ($this->values && !key_exists((string) $this->value, $this->values)) {
            throw new \InvalidArgumentException('Value is not valid');
        }

        $this->calculator->triggerInputSet($this->name);
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        if (is_callable($this->label)) {
            return call_user_func($this->label, $this->calculator->getInputsData());
        } else {
            return $this->label;
        }
    }
}