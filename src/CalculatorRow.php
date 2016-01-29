<?php
namespace nvbooster\Calculator;

/**
 * CalculatorRow
 *
 * @author nvb <nvb@aproxima.ru>
 */
class CalculatorRow extends CalculatorField
{
    private $label;
    private $visible;
    private $note;

    private $currencies;

    private $visibleCallback;
    private $labelCallback;
    private $Callback;

    /**
     * @param string     $name
     * @param Calculator $calculator
     * @param array      $options
     */
    public function __construct($name, Calculator $calculator, $options = array())
    {


        $defaults = array(
            'values' => false,
            'base' => Calculator::CURRENCY_RUB,
            'label' => ucfirst($name),
            'visible' => true,
            'note' => false
        );

        $options = array_merge($defaults, $options);
        if (!key_exists('currencies', $options)) {
            $options['currencies'] = array_unique(array(
                $options['base'],
                $defaults['base']
            ));
        }

        parent::__construct($name, $calculator, $options);

        if (is_callable($options['label'])) {
            $this->label = $defaults['label'];
            $this->labelCallback = $options['label'];
        } else {
            $this->label = (string) $options['label'];
            $this->labelCallback = false;
        }

        if (is_callable($options['visible'])) {
            $this->visible = $defaults['visible'];
            $this->visibleCallback = $options['visible'];
        } else {
            $this->visible = (bool) $options['visible'];
            $this->visibleCallback = false;
        }

        if (is_callable($options['note'])) {
            $this->note = $defaults['note'];
            $this->noteCallback = $options['note'];
        } else {
            $this->note = $options['note'];
            $this->noteCallback = false;
        }

        $this->currencies = array_intersect(
            array_unique($options['currencies']),
            $calculator->getCurrencies()
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see \nvbooster\Calculator\CalculatorField::recalculate()
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

        $fieldParams[$this->name] = $this->value;

        if ($this->visibleCallback) {
            $this->visible = call_user_func($this->visibleCallback, $inputParams, $fieldParams);
        }

        if ($this->labelCallback) {
            $this->label = call_user_func($this->labelCallback, $inputParams, $fieldParams);
        }

        if ($this->noteCallback) {
            $this->note = call_user_func($this->noteCallback, $inputParams, $fieldParams);
        }
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @return boolean
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * @return array
     */
    public function getCurrencies()
    {
        return $this->currencies;
    }
}