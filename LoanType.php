<?php

abstract class LoanType
{
    abstract public function rate(): float;
    abstract public function label(): string;

    public static function create(int $_choice): self
    {
        return match($_choice) {
            1 => new PersonalLoan(),
            2 => new HomeLoan(),
            3 => new CarLoan(),
            4 => new EducationLoan(),
            default => throw new Exception("Invalid loan type choice: '{$_choice}'.")
        };
    }
}

class PersonalLoan extends LoanType
{
    private const RATE = 0.12;

    public function rate(): float
    {
        return self::RATE;
    }

    public function label(): string
    {
        return 'Personal Loan';
    }
}

class HomeLoan extends LoanType
{
    private const RATE = 0.08;

    public function rate(): float
    {
        return self::RATE;
    }

    public function label(): string
    {
        return 'Home Loan';
    }
}

class CarLoan extends LoanType
{
    private const RATE = 0.09;

    public function rate(): float
    {
        return self::RATE;
    }

    public function label(): string
    {
        return 'Car Loan';
    }
}

class EducationLoan extends LoanType
{
    private const RATE = 0.095;

    public function rate(): float
    {
        return self::RATE;
    }

    public function label(): string
    {
        return 'Education Loan';
    }
}
