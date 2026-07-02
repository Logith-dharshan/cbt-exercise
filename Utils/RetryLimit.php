<?php

trait RetryLimit
{
    private const MAX_ATTEMPTS = 3;
    /**
     * Increments the attempt counter and exits the program once
     * MAX_ATTEMPTS has been reached.
     *
     * @param int $_attempts The current attempt count.
     * @return void
     */
    private function registerAttempt(int &$_attempts): void
    {
        $_attempts++;

        if ($_attempts >= self::MAX_ATTEMPTS) {
            echo "\nToo many invalid attempts. Exiting program.\n";

            exit(1);
        }
    }
}
