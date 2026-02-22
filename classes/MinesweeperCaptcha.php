<?php
/**
 * Minesweeper CAPTCHA — clear all safe cells to pass.
 *
 * PHP Concepts: class constants, private methods, pass-by-reference (&),
 *               while loop, nested for loops, BFS flood-fill, PRG pattern.
 */
class MinesweeperCaptcha extends Captcha
{
    protected string $type = 'minesweeper';
    private const R = 9, C = 9, M = 10;

    public function __construct()
    {
        if (!isset($_SESSION['ms'])) {
            $this->resetState();
        }
    }

    public function getLabel(): string { return '&#128163; Minesweeper'; }

    public function handlePost(): ?string
    {
        $ms     = &$_SESSION['ms'];
        $action = $_POST['ms_action'] ?? '';

        if ($action === 'reset') {
            $this->resetState();
        } elseif ($action === 'toggle_flag') {
            $ms['flag_mode'] = !$ms['flag_mode'];
        } elseif (in_array($action, ['reveal', 'flag']) && !$ms['game_over'] && !$ms['won']) {
            [$r, $c] = array_map('intval', explode(',', $_POST['cell'] ?? '0,0'));

            if ($action === 'reveal' && !$ms['flagged'][$r][$c]) {
                if ($ms['first_click']) {
                    $this->placeMines($r, $c);
                    $ms['first_click'] = false;
                }
                if ($ms['grid'][$r][$c] === -1) {
                    $ms['revealed'][$r][$c] = true;
                    $ms['game_over'] = true;
                    $this->penalize();
                    $_SESSION['flash'] = "Boom! ({$_SESSION['skips_left']} skip(s) left)";
                } else {
                    $this->floodFill($r, $c);
                    if ($this->checkWin()) { $ms['won'] = true; $this->pass(); }
                }
            } elseif ($action === 'flag' && !$ms['revealed'][$r][$c]) {
                $ms['flagged'][$r][$c] = !$ms['flagged'][$r][$c];
            }
        }

        header('Location: ?page=captcha-session');
        exit;
    }

    public function render(string $error): void
    {
        $ms    = $_SESSION['ms'];
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $mode = $ms['flag_mode'];

        $flagged = 0;
        foreach ($ms['flagged'] as $row) { $flagged += count(array_filter($row)); }
        ?>
        <div class="ms-bar">
            <span class="ms-counter">&#128163; <?= self::M - $flagged ?></span>
            <span class="ms-counter"><?= $mode ? '&#128681; Flag' : '&#128270; Reveal' ?></span>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-error"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <?php if ($ms['game_over']): ?>
            <?php $this->renderGrid($ms, true); ?>
            <form method="POST" class="ms-controls">
                <input type="hidden" name="captcha_type" value="minesweeper">
                <input type="hidden" name="ms_action" value="reset">
                <button type="submit" class="btn btn-primary btn-block">&#128260; New Game</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="captcha_type" value="minesweeper">
                <input type="hidden" name="ms_action" value="<?= $mode ? 'flag' : 'reveal' ?>">
                <?php $this->renderGrid($ms, false); ?>
            </form>
            <div class="ms-controls-row">
                <form method="POST"><input type="hidden" name="captcha_type" value="minesweeper">
                    <input type="hidden" name="ms_action" value="toggle_flag">
                    <button type="submit" class="btn btn-sm <?= $mode ? 'btn-active' : '' ?>">
                        &#128681; <?= $mode ? 'Reveal Mode' : 'Flag Mode' ?>
                    </button>
                </form>
                <form method="POST"><input type="hidden" name="captcha_type" value="minesweeper">
                    <input type="hidden" name="ms_action" value="reset">
                    <button type="submit" class="btn btn-sm">&#128260; New Game</button>
                </form>
            </div>
            <p class="ms-hint">Click a cell to <?= $mode ? 'flag/unflag' : 'reveal' ?> it.</p>
        <?php endif;
    }

    // ── Rendering ──────────────────────────────────────────────

    private function renderGrid(array $ms, bool $over): void
    {
        echo '<div class="ms-grid-wrap"><div class="ms-grid">';
        for ($r = 0; $r < self::R; $r++) {
            for ($c = 0; $c < self::C; $c++) {
                $v   = $ms['grid'][$r][$c];
                $rev = $ms['revealed'][$r][$c];
                $flg = $ms['flagged'][$r][$c];

                if ($rev && $v === -1) {
                    echo "<div class='ms-cell mine-hit'>&#128163;</div>";
                } elseif ($rev) {
                    $n = $v > 0 ? "<span class='n$v'>$v</span>" : '';
                    echo "<div class='ms-cell revealed'>$n</div>";
                } elseif ($over && $v === -1) {
                    echo "<div class='ms-cell mine-shown'>&#128163;</div>";
                } elseif ($over) {
                    echo "<div class='ms-cell hidden'>" . ($flg ? '&#128681;' : '') . "</div>";
                } elseif ($flg) {
                    echo "<button type='submit' name='cell' value='$r,$c' class='ms-cell hidden flagged'>&#128681;</button>";
                } else {
                    echo "<button type='submit' name='cell' value='$r,$c' class='ms-cell hidden'></button>";
                }
            }
        }
        echo '</div></div>';
    }

    // ── Game logic ─────────────────────────────────────────────

    private function resetState(): void
    {
        $_SESSION['ms'] = [
            'grid'     => array_fill(0, self::R, array_fill(0, self::C, 0)),
            'revealed' => array_fill(0, self::R, array_fill(0, self::C, false)),
            'flagged'  => array_fill(0, self::R, array_fill(0, self::C, false)),
            'game_over' => false, 'won' => false,
            'first_click' => true, 'flag_mode' => false,
        ];
    }

    private function placeMines(int $sr, int $sc): void
    {
        $ms = &$_SESSION['ms'];
        $placed = 0;
        while ($placed < self::M) {
            $r = rand(0, self::R - 1);
            $c = rand(0, self::C - 1);
            if ($ms['grid'][$r][$c] === -1 || (abs($r - $sr) <= 1 && abs($c - $sc) <= 1)) continue;
            $ms['grid'][$r][$c] = -1;
            $placed++;
        }
        for ($r = 0; $r < self::R; $r++)
            for ($c = 0; $c < self::C; $c++) {
                if ($ms['grid'][$r][$c] === -1) continue;
                $n = 0;
                for ($dr = -1; $dr <= 1; $dr++)
                    for ($dc = -1; $dc <= 1; $dc++) {
                        $nr = $r + $dr; $nc = $c + $dc;
                        if ($nr >= 0 && $nr < self::R && $nc >= 0 && $nc < self::C && $ms['grid'][$nr][$nc] === -1) $n++;
                    }
                $ms['grid'][$r][$c] = $n;
            }
    }

    private function floodFill(int $r, int $c): void
    {
        $ms = &$_SESSION['ms'];
        $q  = [[$r, $c]];
        while ($q) {
            [$cr, $cc] = array_shift($q);
            if ($cr < 0 || $cr >= self::R || $cc < 0 || $cc >= self::C) continue;
            if ($ms['revealed'][$cr][$cc] || $ms['flagged'][$cr][$cc]) continue;
            $ms['revealed'][$cr][$cc] = true;
            if ($ms['grid'][$cr][$cc] === 0)
                for ($dr = -1; $dr <= 1; $dr++)
                    for ($dc = -1; $dc <= 1; $dc++)
                        if ($dr || $dc) $q[] = [$cr + $dr, $cc + $dc];
        }
    }

    private function checkWin(): bool
    {
        $ms = $_SESSION['ms'];
        $safe = $rev = 0;
        for ($r = 0; $r < self::R; $r++)
            for ($c = 0; $c < self::C; $c++) {
                if ($ms['grid'][$r][$c] !== -1) $safe++;
                if ($ms['revealed'][$r][$c] && $ms['grid'][$r][$c] !== -1) $rev++;
            }
        return $rev === $safe;
    }
}
