<?php

$class = new nmContentClass;

$classList = $class->getList();

echo json_encode($classList);

$Result['pagelayout'] = false;