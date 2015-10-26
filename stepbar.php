<ol class="wizard-progress clear">

<li class="<?php if ($step == 1) echo "active "; if ($step >= 1) echo "done "; ?> ">
<span class="step-name">
<a href="file.php">Upload</a>
</span>
<span class="visuallyhidden">Step </span><span class="step-num">1</span>
</li>

<li class="<?php if ($step == 2) echo "active "; if ($step >= 2) echo "done "; ?> ">
<span class="step-name">
<a href="analyse.php">Analyse</a>
</span>
<span class="visuallyhidden">Step </span><span class="step-num">2</span>
</li>

<li class="<?php if ($step == 3) echo "active "; if ($step >= 3) echo "done "; ?> ">
<span class="step-name">
<a href="map.php">Visualise</a>
</span>
<span class="visuallyhidden">Step </span><span class="step-num">3</span>
</li>

</ol>
