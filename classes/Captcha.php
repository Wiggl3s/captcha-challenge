<?php
/**
 * Abstract base class for all CAPTCHA types.
 *
 *  abstract class, inheritance, static factory method,
 *               sessions ($_SESSION), type declarations, encapsulation.
 */
abstract class Captcha
{
    protected string $type;

    abstract public function getLabel(): string;
    abstract public function handlePost(): ?string;       
    abstract public function render(string $error): void; 

    public function getType(): string { return $this->type; }

    /** Marks captcha as passed and redirects to dashboard. */
    protected function pass(): void
    {
        $_SESSION['captcha_passed'] = true;
        $_SESSION['captcha_type']   = $this->type;
        header('Location: ?page=dashboard');
        exit;
    }

    /** Deducts one skip on wrong answer. */
    protected function penalize(): void
    {
        if ($_SESSION['skips_left'] > 0) {
            $_SESSION['skips_left']--;
        }
    }

    /** Factory: creates the correct subclass from a type string. */
    public static function create(string $type): Captcha
    {
        switch ($type) {
            case 'chess':       return new ChessCaptcha();
            case 'minesweeper': return new MinesweeperCaptcha();
            default:            return new FebruaryCaptcha();
        }
    }
}
