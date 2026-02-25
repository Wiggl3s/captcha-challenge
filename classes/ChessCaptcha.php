<?php
/**
 * Full Chess Match CAPTCHA 
 *
 *   complete chess engine (all pieces, castling, en passant,
 *               promotion), pseudo-legal/legal move gen, attack detection,
 *               greedy bot AI, session-based board state.
 */
class ChessCaptcha extends Captcha
{
    protected string $type = 'chess';

    private const SYM = [
        'K'=>'&#9812;','Q'=>'&#9813;','R'=>'&#9814;','B'=>'&#9815;','N'=>'&#9816;','P'=>'&#9817;',
        'k'=>'&#9818;','q'=>'&#9819;','r'=>'&#9820;','b'=>'&#9821;','n'=>'&#9822;','p'=>'&#9823;',
        '.'=>'',
    ];

    public function __construct()
    {
        if (!isset($_SESSION['chess_game'])) $this->newGame();
    }

    public function getLabel(): string { return '&#9822; Chess Match'; }

    // ── POST ───────────────────────────────────────────────────

    public function handlePost(): ?string
    {
        $g = &$_SESSION['chess_game'];
        if (isset($_POST['chess_reset'])) { $this->newGame(); return null; }
        if ($g['over'] || !isset($_POST['cell'])) return null;

        [$r, $c] = array_map('intval', explode(',', $_POST['cell']));
        $p = $g['board'][$r][$c];

        if ($g['sel'] !== null) {
            $leg = [];
            foreach ($this->legal($g, $g['sel'][0], $g['sel'][1], true) as $m)
                $leg["{$m[0]},{$m[1]}"] = true;

            if (isset($leg["$r,$c"])) {
                $this->applyMove($g, $g['sel'][0], $g['sel'][1], $r, $c);
                $g['sel'] = null;

                if (!$this->anyLegal($g, false)) {
                    $g['over'] = true;
                    if ($this->inCheck($g['board'], false)) {
                        unset($_SESSION['chess_game']);
                        $this->pass();
                    }
                    $this->penalize(); $this->newGame();
                    return "Stalemate — draw! ({$_SESSION['skips_left']} skip(s) left)";
                }

                $this->botMove();

                if (!$this->anyLegal($g, true)) {
                    $g['over'] = true;
                    if ($this->inCheck($g['board'], true)) {
                        $g['msg'] = 'Checkmate — you lose!';
                        $this->penalize();
                        return "Checkmate — you lose! ({$_SESSION['skips_left']} skip(s) left)";
                    }
                    $this->penalize(); $this->newGame();
                    return "Stalemate — draw! ({$_SESSION['skips_left']} skip(s) left)";
                }
            } elseif ($this->isW($p)) {
                $g['sel'] = [$r, $c];
            } else {
                $g['sel'] = null;
            }
        } elseif ($this->isW($p)) {
            $g['sel'] = [$r, $c];
        }

        return null;
    }

    // ── Render ─────────────────────────────────────────────────

    public function render(string $error): void
    {
        $g   = $_SESSION['chess_game'];
        $sel = $g['sel'];
        $leg = [];
        if ($sel !== null)
            foreach ($this->legal($g, $sel[0], $sel[1], true) as $m)
                $leg["{$m[0]},{$m[1]}"] = true;
        $bChk = !$g['over'] && $this->inCheck($g['board'], false);
        $wChk = !$g['over'] && $this->inCheck($g['board'], true);
        ?>
        <p class="captcha-intro">Checkmate the bot to pass!</p>
        <p class="chess-sub">You are White — Bot is Black</p>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php elseif (!empty($g['msg'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($g['msg']) ?></div>
        <?php elseif ($wChk): ?>
            <div class="alert alert-error">You are in check!</div>
        <?php elseif ($bChk): ?>
            <div class="alert alert-info">Check!</div>
        <?php endif; ?>

        <form method="POST">
        <input type="hidden" name="captcha_type" value="chess">
        <div class="chess-board-wrap">
        <table class="chess-board">
        <?php for ($r = 0; $r < 8; $r++): ?><tr>
            <?php for ($c = 0; $c < 8; $c++):
                $p     = $g['board'][$r][$c];
                $cls   = ($r + $c) % 2 === 0 ? 'light' : 'dark';
                $isSel = $sel && $sel[0] === $r && $sel[1] === $c;
                $isLeg = isset($leg["$r,$c"]);
                if ($isSel) $cls .= ' sq-sel';
                if ($isLeg) $cls .= ' sq-leg';
                $ok = !$g['over'] && ($sel !== null
                    ? ($isLeg || $this->isW($p) || $isSel)
                    : $this->isW($p));
            ?>
            <td class="chess-sq <?= $cls ?>">
            <?php if ($ok): ?>
                <button type="submit" name="cell" value="<?= "$r,$c" ?>" class="sq-btn">
                    <?= $p !== '.' ? (self::SYM[$p] ?? '') : ($isLeg ? '&bull;' : '') ?>
                </button>
            <?php else: ?>
                <?= self::SYM[$p] ?? '' ?>
            <?php endif; ?>
            </td>
            <?php endfor; ?>
        </tr><?php endfor; ?>
        </table>
        </div>
        </form>

        <form method="POST" class="chess-ctrl">
            <input type="hidden" name="captcha_type" value="chess">
            <input type="hidden" name="chess_reset" value="1">
            <button type="submit" class="btn btn-sm">Reset Game</button>
        </form>
        <?php
    }

    // ── Setup ──────────────────────────────────────────────────

    private function newGame(): void
    {
        $_SESSION['chess_game'] = [
            'board' => [
                ['r','n','b','q','k','b','n','r'],
                ['p','p','p','p','p','p','p','p'],
                ['.','.','.','.','.','.','.','.'],
                ['.','.','.','.','.','.','.','.'],
                ['.','.','.','.','.','.','.','.'],
                ['.','.','.','.','.','.','.','.'],
                ['P','P','P','P','P','P','P','P'],
                ['R','N','B','Q','K','B','N','R'],
            ],
            'sel' => null, 'over' => false, 'msg' => '',
            'castle' => [true, true, true, true],
            'ep' => null,
        ];
    }

    private function applyMove(array &$g, int $fr, int $fc, int $tr, int $tc): void
    {
        $b = &$g['board'];
        $p = $b[$fr][$fc]; $t = strtoupper($p); $w = $this->isW($p);
        $g['ep'] = null;

        if ($t === 'P' && abs($tr - $fr) === 2)
            $g['ep'] = [(int)(($fr + $tr) / 2), $fc];

        if ($t === 'P' && $fc !== $tc && $b[$tr][$tc] === '.')
            $b[$fr][$tc] = '.';

        if ($t === 'K' && abs($tc - $fc) === 2) {
            if ($tc === 6) { $b[$fr][5] = $b[$fr][7]; $b[$fr][7] = '.'; }
            if ($tc === 2) { $b[$fr][3] = $b[$fr][0]; $b[$fr][0] = '.'; }
        }

        $b[$tr][$tc] = $p; $b[$fr][$fc] = '.';

        if ($t === 'P' && ($tr === 0 || $tr === 7))
            $b[$tr][$tc] = $w ? 'Q' : 'q';

        if ($t === 'K') {
            if ($w) $g['castle'][0] = $g['castle'][1] = false;
            else    $g['castle'][2] = $g['castle'][3] = false;
        }
        foreach ([[7,7,0],[7,0,1],[0,7,2],[0,0,3]] as [$rr,$cc,$ci]) {
            if (($fr === $rr && $fc === $cc) || ($tr === $rr && $tc === $cc))
                $g['castle'][$ci] = false;
        }
    }

    private function botMove(): void
    {
        $g = &$_SESSION['chess_game'];
        $vals = ['P'=>1,'N'=>3,'B'=>3,'R'=>5,'Q'=>9,'p'=>1,'n'=>3,'b'=>3,'r'=>5,'q'=>9];
        $all = []; $best = []; $bv = 0;

        for ($r = 0; $r < 8; $r++)
            for ($c = 0; $c < 8; $c++) {
                $p = $g['board'][$r][$c];
                if ($p === '.' || $this->isW($p)) continue;
                foreach ($this->legal($g, $r, $c, false) as [$nr, $nc]) {
                    $all[] = [$r, $c, $nr, $nc];
                    $v = $vals[$g['board'][$nr][$nc]] ?? 0;
                    if ($v > $bv) { $bv = $v; $best = [[$r,$c,$nr,$nc]]; }
                    elseif ($v === $bv && $v > 0) $best[] = [$r,$c,$nr,$nc];
                }
            }

        if (!$all) return;
        $pick = $best ? $best[array_rand($best)] : $all[array_rand($all)];
        $this->applyMove($g, $pick[0], $pick[1], $pick[2], $pick[3]);
    }

    // ── Engine ─────────────────────────────────────────────────

    private function isW(string $p): bool { return $p >= 'A' && $p <= 'Z'; }

    private function pseudo(array $st, int $r, int $c): array
    {
        $b = $st['board'];
        $p = $b[$r][$c]; $w = $this->isW($p); $t = strtoupper($p);
        $mv = [];

        if ($t === 'P') {
            $d = $w ? -1 : 1; $s = $w ? 6 : 1;
            $nr = $r + $d;
            if ($nr >= 0 && $nr <= 7 && $b[$nr][$c] === '.') {
                $mv[] = [$nr, $c];
                if ($r === $s && $b[$r + 2*$d][$c] === '.') $mv[] = [$r + 2*$d, $c];
            }
            foreach ([-1, 1] as $dc) {
                $nc = $c + $dc;
                if ($nc < 0 || $nc > 7 || $nr < 0 || $nr > 7) continue;
                if ($b[$nr][$nc] !== '.' && $w !== $this->isW($b[$nr][$nc])) $mv[] = [$nr, $nc];
                if (!empty($st['ep']) && $st['ep'][0] === $nr && $st['ep'][1] === $nc) $mv[] = [$nr, $nc];
            }
        } elseif ($t === 'N') {
            foreach ([[-2,-1],[-2,1],[-1,-2],[-1,2],[1,-2],[1,2],[2,-1],[2,1]] as [$dr,$dc]) {
                $nr = $r+$dr; $nc = $c+$dc;
                if ($nr < 0 || $nr > 7 || $nc < 0 || $nc > 7) continue;
                if ($b[$nr][$nc] !== '.' && $w === $this->isW($b[$nr][$nc])) continue;
                $mv[] = [$nr, $nc];
            }
        } elseif ($t === 'K') {
            for ($dr = -1; $dr <= 1; $dr++) for ($dc = -1; $dc <= 1; $dc++) {
                if (!$dr && !$dc) continue;
                $nr = $r+$dr; $nc = $c+$dc;
                if ($nr < 0 || $nr > 7 || $nc < 0 || $nc > 7) continue;
                if ($b[$nr][$nc] !== '.' && $w === $this->isW($b[$nr][$nc])) continue;
                $mv[] = [$nr, $nc];
            }
            $rk = $w ? 7 : 0;
            if ($r === $rk && $c === 4) {
                $ki = $w ? 0 : 2;
                if (!empty($st['castle'][$ki]) && $b[$rk][5] === '.' && $b[$rk][6] === '.'
                    && !$this->sqAtt($b, $rk, 4, !$w) && !$this->sqAtt($b, $rk, 5, !$w))
                    $mv[] = [$rk, 6];
                $qi = $w ? 1 : 3;
                if (!empty($st['castle'][$qi]) && $b[$rk][1] === '.' && $b[$rk][2] === '.' && $b[$rk][3] === '.'
                    && !$this->sqAtt($b, $rk, 4, !$w) && !$this->sqAtt($b, $rk, 3, !$w))
                    $mv[] = [$rk, 2];
            }
        } else {
            $dirs = [];
            if ($t !== 'R') $dirs = [[1,1],[1,-1],[-1,1],[-1,-1]];
            if ($t !== 'B') $dirs = array_merge($dirs, [[0,1],[0,-1],[1,0],[-1,0]]);
            foreach ($dirs as [$dr, $dc])
                for ($i = 1; $i < 8; $i++) {
                    $nr = $r+$dr*$i; $nc = $c+$dc*$i;
                    if ($nr < 0 || $nr > 7 || $nc < 0 || $nc > 7) break;
                    $sq = $b[$nr][$nc];
                    if ($sq === '.') { $mv[] = [$nr, $nc]; continue; }
                    if ($w !== $this->isW($sq)) $mv[] = [$nr, $nc];
                    break;
                }
        }

        return $mv;
    }

    private function sqAtt(array $b, int $tr, int $tc, bool $byW): bool
    {
        for ($r = 0; $r < 8; $r++) for ($c = 0; $c < 8; $c++) {
            $p = $b[$r][$c];
            if ($p === '.' || $this->isW($p) !== $byW) continue;
            $t = strtoupper($p);
            $dr = $tr - $r; $dc = $tc - $c; $ar = abs($dr); $ac = abs($dc);

            if ($t === 'P' && $dr === ($byW ? -1 : 1) && $ac === 1) return true;
            if ($t === 'N' && (($ar === 2 && $ac === 1) || ($ar === 1 && $ac === 2))) return true;
            if ($t === 'K' && $ar <= 1 && $ac <= 1 && ($ar + $ac > 0)) return true;

            if (($t === 'B' || $t === 'Q') && $ar === $ac && $ar > 0) {
                $sr = $dr > 0 ? 1 : -1; $sc = $dc > 0 ? 1 : -1; $ok = true;
                for ($i = 1; $i < $ar; $i++)
                    if ($b[$r+$i*$sr][$c+$i*$sc] !== '.') { $ok = false; break; }
                if ($ok) return true;
            }
            if (($t === 'R' || $t === 'Q') && ($dr === 0 || $dc === 0) && ($ar + $ac > 0)) {
                $sr = $dr <=> 0; $sc = $dc <=> 0; $dist = max($ar, $ac); $ok = true;
                for ($i = 1; $i < $dist; $i++)
                    if ($b[$r+$i*$sr][$c+$i*$sc] !== '.') { $ok = false; break; }
                if ($ok) return true;
            }
        }
        return false;
    }

    private function sim(array $b, int $fr, int $fc, int $tr, int $tc): array
    {
        $p = $b[$fr][$fc]; $t = strtoupper($p); $w = $this->isW($p);
        if ($t === 'P' && $fc !== $tc && $b[$tr][$tc] === '.') $b[$fr][$tc] = '.';
        if ($t === 'K' && abs($tc - $fc) === 2) {
            if ($tc === 6) { $b[$fr][5] = $b[$fr][7]; $b[$fr][7] = '.'; }
            if ($tc === 2) { $b[$fr][3] = $b[$fr][0]; $b[$fr][0] = '.'; }
        }
        $b[$tr][$tc] = $p; $b[$fr][$fc] = '.';
        if ($t === 'P' && ($tr === 0 || $tr === 7)) $b[$tr][$tc] = $w ? 'Q' : 'q';
        return $b;
    }

    private function inCheck(array $b, bool $w): bool
    {
        $ch = $w ? 'K' : 'k';
        for ($r = 0; $r < 8; $r++) for ($c = 0; $c < 8; $c++)
            if ($b[$r][$c] === $ch) return $this->sqAtt($b, $r, $c, !$w);
        return false;
    }

    private function legal(array $st, int $r, int $c, bool $w): array
    {
        $out = [];
        foreach ($this->pseudo($st, $r, $c) as [$nr, $nc]) {
            if (!$this->inCheck($this->sim($st['board'], $r, $c, $nr, $nc), $w))
                $out[] = [$nr, $nc];
        }
        return $out;
    }

    private function anyLegal(array $st, bool $w): bool
    {
        for ($r = 0; $r < 8; $r++) for ($c = 0; $c < 8; $c++) {
            if ($st['board'][$r][$c] === '.' || $this->isW($st['board'][$r][$c]) !== $w) continue;
            if ($this->legal($st, $r, $c, $w)) return true;
        }
        return false;
    }
}
