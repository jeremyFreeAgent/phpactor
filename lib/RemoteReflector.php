<?php

namespace Phpactor;

use Symfony\Component\Process\Process;

class RemoteReflector
{
    private $bootstrap;
    private $file;

    public function __construct($bootstrap, $file)
    {
        $this->bootstrap = $bootstrap;
        $this->file = $file;
    }

    public function reflect()
    {
        $bootstrap = $this->bootstrap;
        $file = $this->file;
         
        $script = <<<EOT
<?php
\$bootstrap = '$bootstrap';
\$file = '$file';
if (\$bootstrap) {
    require_once(\$bootstrap);
}

\$originalClasses = get_declared_classes();
require(\$file);
\$classes = array_diff(get_declared_classes(), \$originalClasses);

\$reflections = array();

\$classMetas = array();
foreach (\$classes as \$class) {
    \$reflection = new ReflectionClass(\$class);
    \$classMeta = array();
    \$classMeta['name'] = \$reflection->getName();
    if (\$reflection->getParentClass()) {
        \$classMeta['parent'] = \$reflection->getParentClass()->getName();
    }
    \$classMeta['methods'] = array();

    foreach (\$reflection->getMethods() as \$method) {
        \$classMeta['methods'][] = array(
            'name' => \$method->getName(),
        );
    }
    \$classMetas[] = \$classMeta;
}

echo json_encode(\$classMetas);
exit(0);
EOT
        ;

        $tmpName = tempnam(sys_get_temp_dir(), 'phpfactor_reflection');
        file_put_contents($tmpName, $script);

        $process = new Process(PHP_BINARY . ' ' . $tmpName);
        $process->run();

        if (false === $process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Could not execute script: %s %s',
                $process->getErrorOutput(),
                $process->getOutput()
            ));
        }

        $output = $process->getOutput();

        if ($output) {
            $reflection = json_decode($output, true);
        }
    }
}
