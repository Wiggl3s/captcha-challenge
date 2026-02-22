<?php
/**
 * February Days CAPTCHA — user must list every day of February.
 *
 *  class inheritance, explode/array_map/sort, range(),
 *               ternary operator, date(), type casting.
 */
class FebruaryCaptcha extends Captcha
{
    protected string $type = 'february';

    public function getLabel(): string { return '&#128197; February Days'; }

    public function handlePost(): ?string
    {
        $input = trim($_POST['february_input'] ?? '');
        $parts = array_filter(explode(',', $input), fn($v) => trim($v) !== '');
        $days  = array_map('intval', $parts);
        sort($days);

        $count = $this->dayCount();

        if ($days === range(1, $count)) {
            $this->pass(); // redirects, never returns
        }

        $this->penalize();
        $s = $_SESSION['skips_left'];
        return "Incorrect. Expected $count days for " . date('Y') . ". ($s skip(s) left)";
    }

    public function render(string $error): void
    {
        $year = date('Y');
        $leap = $this->isLeap() ? '(Leap year!)' : '(Not a leap year)';
        $val  = htmlspecialchars($_POST['february_input'] ?? '');
        ?>
        <p class="captcha-intro">List all the days of February <?= $year ?> <?= $leap ?></p>
        <form method="POST">
            <input type="hidden" name="captcha_type" value="february">
            <div class="form-group">
                <label for="feb">Days (comma-separated)</label>
                <textarea id="feb" name="february_input" class="feb-textarea"
                          placeholder="e.g. 1, 2, 3, 4, 5 ..." rows="3"><?= $val ?></textarea>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-block">Verify</button>
        </form>
        <?php
    }

    private function dayCount(): int  { return $this->isLeap() ? 29 : 28; }

    private function isLeap(): bool
    {
        $y = (int) date('Y');
        return ($y % 4 === 0 && $y % 100 !== 0) || ($y % 400 === 0);
    }
}
