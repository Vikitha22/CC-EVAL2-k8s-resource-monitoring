<?php

echo "<h1>Resource Monitoring Experiment</h1>";

$total = 0;

for ($i = 0; $i < 10000000; $i++) {
    $total += sqrt($i);
}

echo "<p>Calculation completed!</p>";
echo "<p>Result: $total</p>";

?>
