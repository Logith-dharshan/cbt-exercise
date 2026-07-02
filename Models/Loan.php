<?php

class Loan // implements JsonSerializable
{
    private string $loan_type;
    private float $loan_amount;
    private int $loan_tenure;
    private float $monthly_emi;
    private float $total_interest;
    private float $total_repayment;

    public function __construct(
        string $_loan_type,
        float $_loan_amount,
        int $_loan_tenure,
        float $_monthly_emi,
        float $_total_interest,
        float $_total_repayment
    ) {
        $this->setLoanType($_loan_type);
        $this->setLoanAmount($_loan_amount);
        $this->setLoanTenure($_loan_tenure);
        $this->setMonthlyEmi($_monthly_emi);
        $this->setTotalInterest($_total_interest);
        $this->setTotalRepayment($_total_repayment);
    }

    /**
     * Summary of setLoanType
     * @param string $_loan_type
     * @return void
     */
    public function setLoanType(string $_loan_type): void
    {
        $this->loan_type = $_loan_type;
    }

    /**
     * Summary of setLoanAmount
     * @param float $_loan_amount
     * @return void
     */
    public function setLoanAmount(float $_loan_amount): void
    {
        $this->loan_amount = $_loan_amount;
    }

    /**
     * Summary of setLoanTenure
     * @param int $_loan_tenure
     * @return void
     */
    public function setLoanTenure(int $_loan_tenure): void
    {
        $this->loan_tenure = $_loan_tenure;
    }

    /**
     * Summary of setMonthlyEmi
     * @param float $_monthly_emi
     * @return void
     */
    public function setMonthlyEmi(float $_monthly_emi): void
    {
        $this->monthly_emi = round($_monthly_emi, 2);
    }

    /**
     * Summary of setTotalInterest
     * @param float $_total_interest
     * @return void
     */
    public function setTotalInterest(float $_total_interest): void
    {
        $this->total_interest = round($_total_interest, 2);
    }

    /**
     * Summary of setTotalRepayment
     * @param float $_total_repayment
     * @return void
     */
    public function setTotalRepayment(float $_total_repayment): void
    {
        $this->total_repayment = round($_total_repayment, 2);
    }

    public function getLoanType(): string
    {
        return $this->loan_type;
    }

    public function getLoanAmount(): float
    {
        return $this->loan_amount;
    }

    public function getLoanTenure(): int
    {
        return $this->loan_tenure;
    }

    public function getMonthlyEmi(): float
    {
        return $this->monthly_emi;
    }

    public function getTotalInterest(): float
    {
        return $this->total_interest;
    }

    public function getTotalRepayment(): float
    {
        return $this->total_repayment;
    }

    /**
     * Summary of toArray
     * @return array{loan_type: string, loan_amount: float, loan_tenure: int, monthly_emi: float, total_interest: float, total_repayment: float}
     */
    public function toArray(): array
    {
        return [
            'loan_type' => $this->loan_type,
            'loan_amount' => $this->loan_amount,
            'loan_tenure' => $this->loan_tenure,
            'monthly_emi' => $this->monthly_emi,
            'total_interest' => $this->total_interest,
            'total_repayment' => $this->total_repayment,
        ];
    }

    /**
     * Summary of fromArray
     * @param array $_data
     * @return Loan
     */
    public static function fromArray(array $_data): self
    {
        return new self(
            $_data['loan_type'],
            $_data['loan_amount'],
            $_data['loan_tenure'],
            $_data['monthly_emi'],
            $_data['total_interest'],
            $_data['total_repayment']
        );
    }
}
