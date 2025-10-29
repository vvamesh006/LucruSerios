<?php
declare(strict_types=1);
session_start();

class ProdusAlimentatie
{
    private string $denumire;
    private string $producator;
    private string $categorie;
    private int $greutateBruta;
    private float $valoareEnergetica100g;
    private float $pret;
    private float $reducere;

    public function __construct(
        string $denumire = '',
        string $producator = '',
        string $categorie = '',
        int $greutateBruta = 0,
        float $valoareEnergetica100g = 0.0,
        float $pret = 0.0,
        float $reducere = 0.0
    ) {
        $this->setDenumire($denumire);
        $this->setProducator($producator);
        $this->setCategorie($categorie);
        $this->setGreutateBruta($greutateBruta);
        $this->setValoareEnergetica100g($valoareEnergetica100g);
        $this->setPret($pret);
        $this->setReducere($reducere);
    }

    public function setDenumire(string $v): void       { $this->denumire   = trim($v); }
    public function setProducator(string $v): void     { $this->producator = trim($v); }
    public function setCategorie(string $v): void      { $this->categorie  = trim($v); }

    public function setGreutateBruta(int $v): void {
        if ($v <= 0 || $v > 100000) {
            throw new InvalidArgumentException("GreutateBruta trebuie sa fie intre 1 si 100000 grame.");
        }
        $this->greutateBruta = $v;
    }

    public function setValoareEnergetica100g(float $v): void {
        if ($v < 0 || $v > 1000) {
            throw new InvalidArgumentException("ValoareaEnergetica (kcal/100g) trebuie sa fie intre 0 si 1000.");
        }
        $this->valoareEnergetica100g = $v;
    }

    public function setPret(float $v): void {
        if ($v < 0 || $v > 1_000_000) {
            throw new InvalidArgumentException("Pretul trebuie sa fie intre 0 si 1.000.000 lei.");
        }
        $this->pret = $v;
    }

    public function setReducere(float $v): void {
        if ($v < 0 || $v > 100) {
            throw new InvalidArgumentException("Reducerea trebuie sa fie intre 0% si 100%.");
        }
        $this->reducere = $v;
    }

    public function getDenumire(): string { return $this->denumire; }
    public function getProducator(): string { return $this->producator; }
    public function getCategorie(): string { return $this->categorie; }
    public function getGreutateBruta(): int { return $this->greutateBruta; }
    public function getValoareEnergetica100g(): float { return $this->valoareEnergetica100g; }
    public function getPret(): float { return $this->pret; }
    public function getReducere(): float { return $this->reducere; }

    public function pretPeGram(): float {
        return $this->pret / $this->greutateBruta;
    }

    public function valoareEnergeticaTotala(): float {
        return ($this->greutateBruta / 100.0) * $this->valoareEnergetica100g;
    }

    public function pretCuReducere(): float {
        return $this->pret * (1.0 - $this->reducere / 100.0);
    }

    public function toHtmlRow(): string {
        return '<tr>'.
            '<td>'.h($this->denumire).'</td>'.
            '<td>'.h($this->producator).'</td>'.
            '<td>'.h($this->categorie).'</td>'.
            '<td style="text-align:right">'.number_format($this->greutateBruta).'</td>'.
            '<td style="text-align:right">'.number_format($this->valoareEnergetica100g, 1).'</td>'.
            '<td style="text-align:right">'.number_format($this->pret, 2).'</td>'.
            '<td style="text-align:right">'.number_format($this->reducere, 1).'%</td>'.
            '<td style="text-align:right">'.number_format($this->pretPeGram(), 4).'</td>'.
            '<td style="text-align:right">'.number_format($this->valoareEnergeticaTotala(), 1).'</td>'.
            '<td style="text-align:right">'.number_format($this->pretCuReducere(), 2).'</td>'.
            '</tr>';
    }

    public static function fromDelimited(string $line, string $delim = ';'): self {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            throw new InvalidArgumentException("Linie goala/comentariu, ignorata.");
        }
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
        $p = array_map('trim', explode($delim, $line));
        if (count($p) !== 7) {
            throw new InvalidArgumentException("Linie invalida: trebuie 7 campuri separate de '{$delim}'.");
        }
        [$den, $prod, $cat, $gStr, $veStr, $prStr, $redStr] = $p;
        $g   = (int)$gStr;
        $ve  = (float)str_replace(',', '.', $veStr);
        $pr  = (float)str_replace(',', '.', $prStr);
        $red = (float)str_replace(',', '.', $redStr);
        return new self($den, $prod, $cat, $g, $ve, $pr, $red);
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function readProductsFromFile(string $path, string $delim = ';'): array {
    if (!is_file($path)) {
        throw new RuntimeException("Fisierul nu exista: ".h($path));
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException("Nu s-a putut citi fisierul: ".h($path));
    }
    $rez = [];
    foreach ($lines as $line) {
        try {
            $rez[] = ProdusAlimentatie::fromDelimited($line, $delim);
        } catch (InvalidArgumentException $e) {}
    }
    return $rez;
}

if (!isset($_SESSION['produse'])) {
    $_SESSION['produse'] = [];
}

$msg = '';
$err = '';
$lastOp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $op = $_POST['op'] ?? '';
    try {
        if ($op === 'adauga') {
            $den = trim($_POST['denumire'] ?? '');
            $prod= trim($_POST['producator'] ?? '');
            $cat = trim($_POST['categorie'] ?? '');
            $g   = (int)($_POST['greutate'] ?? 0);
            $ve  = (float)str_replace(',', '.', $_POST['val_e100'] ?? '0');
            $pr  = (float)str_replace(',', '.', $_POST['pret'] ?? '0');
            $red = (float)str_replace(',', '.', $_POST['reducere'] ?? '0');
            $obj = new ProdusAlimentatie($den, $prod, $cat, $g, $ve, $pr, $red);
            $_SESSION['produse'][] = serialize($obj);
            $msg = "Produs adaugat.";
            $lastOp = 'adauga';
        } elseif ($op === 'citire_fisier') {
            $cale = trim($_POST['cale'] ?? 'produse.txt');
            $arr  = readProductsFromFile($cale, ';');
            foreach ($arr as $o) {
                $_SESSION['produse'][] = serialize($o);
            }
            $msg = "S-au incarcat ".count($arr)." produse din fisier.";
            $lastOp = 'citire_fisier';
        } elseif ($op === 'top3') {
            $msg = "Afisez top 3 producatori dupa numarul de produse.";
            $lastOp = 'top3';
        } elseif ($op === 'total_reduceri') {
            $msg = "Afisez pretul total (cu reduceri).";
            $lastOp = 'total_reduceri';
        } elseif ($op === 'max_reducere') {
            $msg = "Afisez produsele cu reducerea maxima.";
            $lastOp = 'max_reducere';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }

    if (isset($_POST['reset'])) {
        $_SESSION['produse'] = [];
        $msg = "Lista a fost golită.";
        $lastOp = '';
    }
}

$produse = array_map(fn($s) => unserialize($s), $_SESSION['produse']);

function top3Producatori(array $produse): array {
    $cnt = [];
    foreach ($produse as $p) {
        $k = $p->getProducator();
        $cnt[$k] = ($cnt[$k] ?? 0) + 1;
    }
    arsort($cnt);
    return array_slice($cnt, 0, 3, true);
}

function totalCuReduceri(array $produse): float {
    $sum = 0.0;
    foreach ($produse as $p) {
        $sum += $p->pretCuReducere();
    }
    return $sum;
}

function produseCuReducereMax(array $produse): array {
    if (!$produse) return [];
    $max = max(array_map(fn($p) => $p->getReducere(), $produse));
    return array_values(array_filter($produse, fn($p) => $p->getReducere() == $max));
}
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Produse Alimentație</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
  :root{
    --bg: #f6f7fb;
    --card: #ffffff;
    --text: #0f172a;
    --muted: #6b7280;
    --border: #e5e7eb;
    --accent: #2563eb;
    --accent-2: #9333ea;
    --success: #16a34a;
    --danger: #dc2626;
    --chip-bg: #eef2ff;
    --chip-text: #3730a3;
    --table-stripe: #fafafa;
  }
  .dark{
    --bg: #0b1020;
    --card: #0f172a;
    --text: #e5e7eb;
    --muted: #9aa3b2;
    --border: #1f2937;
    --accent: #60a5fa;
    --accent-2: #c084fc;
    --success: #22c55e;
    --danger: #f87171;
    --chip-bg: #1d2341;
    --chip-text: #c7d2fe;
    --table-stripe: #0c1328;
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    font-family: ui-sans-serif,system-ui,Segoe UI,Roboto,Arial,sans-serif;
    margin:0; padding:24px;
    background: radial-gradient(1200px 600px at 10% -10%, rgba(99,102,241,.12), transparent 40%),
                radial-gradient(1000px 600px at 110% 10%, rgba(37,99,235,.08), transparent 35%),
                var(--bg);
    color:var(--text);
    transition: background .25s ease,color .25s ease;
  }
  .topbar{
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:18px;
  }
  .brand{
    display:flex; gap:10px; align-items:center;
    font-weight:700; letter-spacing:.3px;
  }
  .brand .logo{
    width:36px; height:36px; border-radius:10px;
    background: linear-gradient(135deg,var(--accent),var(--accent-2));
    box-shadow: 0 6px 16px rgba(37,99,235,.25);
  }
  .pill{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px; border:1px solid var(--border);
    background:var(--card); border-radius:999px; color:var(--muted);
    font-size:13px;
  }
  .toggle{
    appearance:none; width:50px; height:28px; border-radius:999px;
    position:relative; background:var(--border); outline:none; cursor:pointer;
    transition: background .2s ease;
  }
  .toggle:before{
    content:""; position:absolute; top:3px; left:3px; width:22px; height:22px;
    border-radius:999px; background:#fff; transition: transform .2s ease;
    box-shadow:0 2px 6px rgba(0,0,0,.15);
  }
  .toggle:checked{ background: linear-gradient(90deg,var(--accent),var(--accent-2)); }
  .toggle:checked:before{ transform: translateX(22px); }

  h1{margin:10px 0 8px; font-size:28px}
  .subtitle{color:var(--muted); margin-bottom:16px}

  .panel{
    background:var(--card); border:1px solid var(--border); border-radius:16px;
    padding:16px; box-shadow: 0 10px 30px rgba(2,6,23,.06);
  }
  .grid{display:grid; gap:16px}
  @media(min-width:1000px){
    .grid{grid-template-columns:2fr 1fr}
  }
  .row{display:flex; gap:12px; flex-wrap:wrap}
  .row>div{flex:1 1 220px; min-width:220px}
  label{display:block; font-size:12px; color:var(--muted); margin:0 0 6px 2px}
  input, select{
    width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px;
    background:var(--bg); color:var(--text)
  }
  input::placeholder{color:var(--muted)}

  .actions{display:flex; gap:10px; margin-top:12px}
  button{
    padding:10px 14px; border:0; border-radius:10px; cursor:pointer; font-weight:600
  }
  .btn-primary{background: linear-gradient(90deg,var(--accent),var(--accent-2)); color:#fff}
  .btn-ghost{background:transparent; color:var(--danger); border:1px solid var(--border)}
  .msg,.err{
    padding:10px 12px; border-radius:12px; display:inline-flex; align-items:center; gap:10px;
    margin:12px 0; font-weight:500
  }
  .msg{background:rgba(34,197,94,.12); color:var(--success); border:1px solid rgba(34,197,94,.25)}
  .err{background:rgba(248,113,113,.12); color:var(--danger); border:1px solid rgba(248,113,113,.25)}

  table{width:100%; border-collapse:separate; border-spacing:0; font-size:14px}
  thead th{
    position:sticky; top:0; z-index:1;
    background:linear-gradient(180deg,rgba(0,0,0,0.02),transparent), var(--card);
    color:var(--muted); font-weight:700; text-align:left; padding:10px 12px; border-bottom:1px solid var(--border)
  }
  tbody td{padding:10px 12px; border-bottom:1px solid var(--border)}
  tbody tr:nth-child(even){ background: var(--table-stripe); }
  tbody tr:hover{ outline:2px solid rgba(37,99,235,.18); outline-offset:-2px; transition:.15s }

  .cards{display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px}
  .card{background:var(--card); border:1px solid var(--border); border-radius:16px; padding:14px; box-shadow: 0 6px 20px rgba(2,6,23,.05)}
  .card h3{margin:0 0 8px; font-size:18px}
  .muted{color:var(--muted)}
  .inline{display:inline-block}

  td:nth-child(3){
    font-weight:600;
    background: var(--chip-bg);
    color: var(--chip-text);
    padding:6px 10px; border-radius:999px; display:inline-block
  }
  td:nth-child(7){ font-weight:700 }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="brand">
      <div class="logo" aria-hidden="true"></div>
      <div>Produse Alimentație</div>
    </div>
    <div class="pill">
      <span id="count"><?= count($produse) ?></span> produse în memorie
      <input id="themeToggle" class="toggle" type="checkbox" aria-label="Comută tema" />
    </div>
  </div>

  <div class="grid">
    <div class="panel">
      <h1>Gestionare Produse</h1>
      <div class="subtitle">Adaugă rapid din formular sau încarcă din fișier.</div>

      <form method="post">
        <div class="row">
          <div>
            <label for="denumire">Denumire</label>
            <input type="text" name="denumire" id="denumire" placeholder="Ex: Telemea de vacă">
          </div>
          <div>
            <label for="producator">Producător</label>
            <input type="text" name="producator" id="producator" placeholder="Ex: Lacto SRL">
          </div>
          <div>
            <label for="categorie">Categorie</label>
            <input type="text" name="categorie" id="categorie" placeholder="Ex: lactate, mezeluri, legume">
          </div>
          <div>
            <label for="greutate">Greutate brută (g)</label>
            <input type="number" name="greutate" id="greutate" min="1" max="100000" step="1" placeholder="Ex: 350">
          </div>
          <div>
            <label for="val_e100">Valoare energetică (kcal/100g)</label>
            <input type="number" name="val_e100" id="val_e100" min="0" max="1000" step="0.1" placeholder="Ex: 280">
          </div>
          <div>
            <label for="pret">Preț (lei)</label>
            <input type="number" name="pret" id="pret" min="0" step="0.01" placeholder="Ex: 39.90">
          </div>
          <div>
            <label for="reducere">Reducere (%)</label>
            <input type="number" name="reducere" id="reducere" min="0" max="100" step="0.1" placeholder="Ex: 15">
          </div>
          <div>
            <label for="cale">Cale fișier</label>
            <input type="text" name="cale" id="cale" value="produse.txt">
          </div>
          <div>
            <label for="op">Acțiune</label>
            <select name="op" id="op">
              <option value="adauga">1. Adaugă produsul din formular</option>
              <option value="citire_fisier">2. Citește produsele din fișier</option>
              <option value="top3">3. Afișează top 3 producători</option>
              <option value="total_reduceri">4. Afișează prețul total (cu reduceri)</option>
              <option value="max_reducere">5. Afișează produsele cu reducerea maximă</option>
            </select>
          </div>
        </div>
        <div class="actions">
          <button class="btn-primary" type="submit">Execută</button>
          <button class="btn-ghost" type="submit" name="reset" value="1" onclick="return confirm('Golești lista din sesiune?');">Golește lista</button>
        </div>
        <p class="muted" style="margin-top:10px">Liniile din fișier pot fi comentate cu „#”. Separator „;”.</p>
      </form>

      <?php if ($msg): ?><div class="msg">✅ <?=h($msg)?></div><?php endif; ?>
      <?php if ($err): ?><div class="err">⚠️ <?=h($err)?></div><?php endif; ?>
    </div>

    <!-- Panoul „Rezultat” care arată doar pentru opțiunile 3/4/5 -->
    <div class="panel">
      <h2 style="margin:0 0 12px">Rezultat</h2>

      <?php if ($lastOp === 'top3'): ?>
        <div class="cards">
          <div class="card">
            <h3>3. Top 3 producători</h3>
            <?php
              $top = top3Producatori($produse);
              if ($top) {
                echo "<ol style='margin:0 0 6px 18px'>";
                foreach ($top as $nume => $cnt) {
                  echo "<li>".h($nume)." — <span class='muted'>{$cnt} prod.</span></li>";
                }
                echo "</ol>";
              } else {
                echo "<div class='muted'>Nu există date.</div>";
              }
            ?>
          </div>
        </div>

      <?php elseif ($lastOp === 'total_reduceri'): ?>
        <div class="cards">
          <div class="card">
            <h3>4. Preț total (cu reduceri)</h3>
            <div style="font-size:22px; font-weight:800">
              <?= number_format(totalCuReduceri($produse), 2) ?> lei
            </div>
            <div class="muted">Suma prețurilor după reduceri</div>
          </div>
        </div>

      <?php elseif ($lastOp === 'max_reducere'): ?>
        <div class="cards">
          <div class="card">
            <h3>5. Reducere maximă</h3>
            <?php
              $max = produseCuReducereMax($produse);
              if ($max) {
                $maxVal = $max[0]->getReducere();
                echo "<div style='font-size:22px; font-weight:800'>".number_format($maxVal,1)."%</div>";
                echo "<ul style='margin:6px 0 0 16px'>";
                foreach ($max as $p) {
                  echo "<li>".h($p->getDenumire())." — ".h($p->getProducator())."</li>";
                }
                echo "</ul>";
              } else {
                echo "<div class='muted'>Nu există date.</div>";
              }
            ?>
          </div>
        </div>

      <?php else: ?>
        <!-- Pentru opțiunile 1/2 sau inițial, nu afișăm nimic aici -->
      <?php endif; ?>
    </div>
  </div>

  <div class="panel" style="margin-top:16px">
    <h2 style="margin:0 0 12px">Produse în memorie (<?=count($produse)?>)</h2>
    <div style="overflow:auto; border-radius:12px; border:1px solid var(--border)">
      <table>
        <thead>
          <tr>
            <th>Denumire</th>
            <th>Producător</th>
            <th>Categorie</th>
            <th>Greutate (g)</th>
            <th>kcal/100g</th>
            <th>Preț (lei)</th>
            <th>Reducere</th>
            <th>Preț/gram</th>
            <th>kcal totale</th>
            <th>Preț cu reducere</th>
          </tr>
        </thead>
        <tbody>
        <?php
          if (!$produse) {
            echo '<tr><td colspan="10" class="muted">Nu există produse încă.</td></tr>';
          } else {
            foreach ($produse as $p) { echo $p->toHtmlRow(); }
          }
        ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    (function(){
      const key = 'theme:dark';
      const body = document.body;
      const tgl = document.getElementById('themeToggle');
      const saved = localStorage.getItem(key);
      if(saved === '1'){ body.classList.add('dark'); if(tgl) tgl.checked = true; }
      if(tgl){
        tgl.addEventListener('change', function(){
          body.classList.toggle('dark', this.checked);
          localStorage.setItem(key, this.checked ? '1' : '0');
        });
      }
    })();
  </script>
</body>
</html>
