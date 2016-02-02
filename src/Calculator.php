<?php
namespace nvbooster\Calculator;

/**
 * Calculator
 *
 * @author nvb <nvb@aproxima.ru>
 */
class Calculator
{
    const CURRENCY_NONE = 0;
    const CURRENCY_USD = 1;
    const CURRENCY_YEN = 2;
    const CURRENCY_RUB = 3;
    const CURRENCY_EUR = 4;

    private $ready;
    private $inputSubscribers;
    private $fieldSubscribers;

    private $fields;
    private $inputs;
    private $rates;
    private $notesIndex;
    private $currencies;

    /**
     * __construct
     *
     * @param array $extraCurrencies
     */
    public function __construct($extraCurrencies = array())
    {
        $this->ready = false;
        $this->inputSubscribers = array();
        $this->fieldSubscribers = array();
        $this->fields = array();
        $this->inputs = array();
        $this->notesIndex = array();

        $currencies = array(self::CURRENCY_RUB, self::CURRENCY_USD, self::CURRENCY_YEN, self::CURRENCY_EUR);

        foreach ($extraCurrencies as $currency) {
            if (!$currency || in_array($currency, $currencies)) {
                throw new \Exception('Duplicate currency index "' . $currency . '".');
            } else {
                $currencies[] = $currency;
            }
        }

        $this->currencies = $currencies;


        $this->rates = array_fill_keys($this->currencies, array_fill_keys($this->currencies, 1));
    }

    /**
     * setReady
     *
     * @return Calculator
     */
    public function setReady()
    {
        if (!$this->ready) {
            $this->ready = true;
            foreach ($this->fields as $field) {
                /* @var $field CalculatorField */
                $field->recalculate();
                $this->triggerFieldChange($field->getName());
            }
        }

        return $this;
    }

    /**
     * Convert
     *
     * @param double  $value
     * @param integer $baseFrom
     * @param integer $baseTo
     *
     * @return double
     */
    public function convert($value, $baseFrom, $baseTo)
    {
        return $value * $this->rates[$baseFrom][$baseTo];
    }

    /**
     * @param string $name
     *
     * @return Calculator
     */
    public function triggerInputSet($name)
    {
        if ($this->ready) {
            foreach ($this->getInputSubscribers($name) as $field) {
                $this->recalculateField($field);
            }
        }

        return $this;
    }

    /**
     * @param string $name
     *
     * @return Calculator
     */
    public function triggerFieldChange($name)
    {
        $this->notesIndex = array();

        foreach ($this->getFieldSubscribers($name) as $field) {
            $this->recalculateField($field);
        }

        return $this;
    }

    /**
     * @param string $name
     * @throws \Exception
     */
    private function recalculateField($name)
    {
        $this->getField($name)->recalculate();
    }

    /**
     * @param string  $name
     * @param array   $options
     * @param boolean $isRow
     *
     * @return Calculator
     * @throws \Exception
     */
    public function addField($name, $options = array(), $isRow = false)
    {
        if ($this->ready) {
            throw new \Exception('Calculator is finalized');
        }

        if (key_exists($name, $this->fields)) {
            throw new \Exception('Field "' . $name . '" is already defined');
        }

        $field = $isRow
            ? new CalculatorRow($name, $this, $options)
            : new CalculatorField($name, $this, $options);

        foreach ($field->getInputDependencies() as $inputDependency) {
            if (!key_exists($inputDependency, $this->inputSubscribers)) {
                $this->inputSubscribers[$inputDependency] = array();
            }

            $this->inputSubscribers[$inputDependency][] = $field->getName();
        }

        foreach ($field->getFieldDependencies() as $fieldDependency) {
            if (!key_exists($fieldDependency, $this->fieldSubscribers)) {
                $this->fieldSubscribers[$fieldDependency] = array();
            }

            $this->fieldSubscribers[$fieldDependency][] = $field->getName();
        }

        $this->fields[$field->getName()] = $field;

        return $this;
    }

    /**
     * @param string $name
     * @param array  $options
     *
     * @return Calculator
     */
    public function addRow($name, $options = array())
    {
        $this->addField($name, $options, true);

        return $this;
    }

    /**
     * @param string $name
     * @param array  $options
     *
     * @return Calculator
     * @throws \Exception
     */
    public function addInput($name, $options = array())
    {
        if ($this->ready) {
            throw new \Exception('Calculator is finalized');
        }

        if (key_exists($name, $this->inputs)) {
            throw new \Exception('Input "' . $name . '" is already defined');
        }

        $input = new CalculatorInput($name, $this, $options);
        $this->inputs[$input->getName()] = $input;

        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return Calculator
     * @throws \Exception
     */
    public function set($name, $value)
    {
        $this->getInput($name)->setValue($value);

        return $this;
    }

    /**
     * @param string $name
     * @throws \Exception
     *
     * @return mixed
     */
    public function get($name)
    {
        if ($name instanceof CalculatorRow) {
            $row = $name;
        } else {
            $row = $this->getField($name);
        }

        if ($row instanceof CalculatorRow) {
            $data = false;

            if ($row->isVisible()) {
                $data = array(
                    'label' => $row->getLabel(),
                    'base' => $row->getBase(),
                    'has_note' => (bool) $row->getNote(),
                    'values' => false
                );
                if (count($row->getCurrencies())) {
                    $data['values'] = array();
                    foreach ($row->getCurrencies() as $currency) {
                        $data['values'][$currency] = $row->getValue($currency);
                    }
                }
            }

            return $data;
        } else {
            trigger_error('Row "' . $name . '" not found, but field exists with same name', E_USER_WARNING);

            return false;
        }
    }

    /**
     * @return array
     */
    public function getAll()
    {
        $calculator = $this;

        return array_map(
            function ($row) use ($calculator) {
                 return $calculator->get($row);
            },
            array_filter(
                $this->fields,
                function ($field) {
                    return ($field instanceof CalculatorRow) && $field->isVisible();
                }
            )
        );
    }

    /**
     * @return array
     */
    public function getInputsData()
    {
        $data = array();
        foreach ($this->inputs as $input) {
            $data[$input->getName()] = $input->getValue();
        }

        return $data;
    }

    /**
     * @param string $name
     *
     * @throws \Exception
     * @return CalculatorInput
     */
    private function getInput($name)
    {
        if (!key_exists($name, $this->inputs)) {
            throw new \Exception('Input "' . $name . '" not found.');
        }

        /* @var $input CalculatorInput */
        return $this->inputs[$name];
    }

    /**
     * @param string $name
     *
     * @return string
     * @throws \Exception
     */
    public function getInputLabel($name)
    {
        return $this->getInput($name)->getLabel();
    }

    /**
     * @param string $name
     *
     * @return integer
     * @throws \Exception
     */
    public function getInputBase($name)
    {
        return $this->getInput($name)->getBase();
    }

    /**
     * @param string $name
     *
     * @return array
     * @throws \Exception
     */
    public function getInputValuesList($name)
    {
        return $this->getInput($name)->getValues();
    }

    /**
     * @param string  $name
     * @param integer $base
     *
     * @return mixed
     * @throws \Exception
     */
    public function getInputValue($name, $base = false)
    {
        return $this->getInput($name)->getValue($base);
    }

    /**
     * @param string $name
     *
     * @throws \Exception
     * @return CalculatorField
     */
    private function getField($name)
    {
        if (!key_exists($name, $this->fields)) {
            throw new \Exception('Field "' . $name . '" not found.');
        }

        return $this->fields[$name];
    }

    /**
     * @param string  $name
     * @param integer $base
     *
     * @return mixed
     * @throws \Exception
     */
    public function getFieldValue($name, $base = false)
    {
        return $this->getField($name)->getValue($base);
    }

    /**
     * @param integer $from
     * @param integer $to
     * @param double  $buy
     * @param double  $sell
     *
     * @return boolean
     */
    public function setRate($from, $to, $buy, $sell = false)
    {
        if (
            !key_exists($from, $this->rates) ||
            !key_exists($to, $this->rates)
        ) {
            trigger_error('Undefined currency index', E_USER_WARNING);

            return false;
        }

        if (!$sell && $buy) {
            $sell = 1 / $buy;
        } else {
            $sell = 1 / $sell;
        }

        $this->rates[$from][$to] = $buy;
        $this->rates[$to][$from] = $sell;

        if ($this->ready) {
            foreach ($this->fields as $field) {
                $this->triggerFieldChange($field->getName());
            }
        }

        return $this;
    }

    /**
     * @param string $name
     *
     * @return boolean|number[]|NULL[]
     * @throws \Exception
     */
    public function getNote($name)
    {
        $note = false;
        /* @var $row CalculatorRow */
        $row = $this->getField($name);

        if ($row instanceof CalculatorRow) {
            if ($row->isVisible() && $row->getNote()) {
                if (false === $index = array_search($row->getName(), $this->notesIndex)) {
                    $this->notesIndex[] = $row->getName();
                    $note = array('index' => count($this->notesIndex), 'note' => $row->getNote());
                } else {
                    $note = array('index' => $index + 1, 'note' => $row->getNote());
                }
            }
        } else {
            trigger_error('Row "' . $name . '" not found, but field exists with same name', E_USER_WARNING);
        }

        return $note;
    }

    /**
     * @return array
     */
    public function getAllNotes()
    {
        $notes = array();
        foreach ($this->notesIndex as $index => $name) {
            $row = $this->fields[$name];
            $notes[$index + 1] = $row->getNote();
        }

        return $notes;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    private function getInputSubscribers($name)
    {
        return key_exists($name, $this->inputSubscribers) ? $this->inputSubscribers[$name] : array();
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    private function getFieldSubscribers($name)
    {
        return key_exists($name, $this->fieldSubscribers) ? $this->fieldSubscribers[$name] : array();
    }

    /**
     * @return array
     */
    public function getCurrencies()
    {
        return $this->currencies;
    }
}