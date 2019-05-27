<?php
namespace Queue\Queue;

use Cake\Core\App;
use Cake\Core\Plugin;
use Cake\Filesystem\Folder;

class TaskFinder
{

    /**
     *
     * @var array|null
     */
    protected $tasks;

    /**
     * Returns all possible Queue tasks.
     *
     * Makes sure that app tasks are prioritized over plugin ones.
     *
     * @return array
     */
    public function allAppAndPluginTasks()
    {
        if ($this->tasks !== null) {
            return $this->tasks;
        }

        $paths = App::path('Shell/Task');
        $this->tasks = [];

        foreach ($paths as $path) {
            $folder = new Folder($path);
            $this->tasks = $this->getAppPaths($folder);
        }
        $plugins = Plugin::loaded();
        foreach ($plugins as $plugin) {
            $pluginPaths = App::path('Shell/Task', $plugin);
            foreach ($pluginPaths as $pluginPath) {
                $folder = new Folder($pluginPath);
                $pluginTasks = $this->getPluginPaths($folder, $plugin);
                $this->tasks = array_merge($this->tasks, $pluginTasks);
            }
        }

        return $this->tasks;
    }

    /**
     *
     * @param \Cake\Filesystem\Folder $folder The directory
     *
     * @return array
     */
    protected function getAppPaths(Folder $folder)
    {
        $res = array_merge($this->tasks, $folder->find('Queue.+\.php'));
        foreach ($res as &$r) {
            $r = basename($r, 'Task.php');
        }

        return $res;
    }

    /**
     *
     * @param \Cake\Filesystem\Folder $folder The directory
     * @param string $plugin The plugin name
     *
     * @return array
     */
    protected function getPluginPaths(Folder $folder, $plugin)
    {
        $res = $folder->find('Queue.+Task\.php');
        foreach ($res as $key => $r) {
            $name = basename($r, 'Task.php');
            if (in_array($name, $this->tasks)) {
                unset($res[$key]);
                continue;
            }
            $res[$key] = $plugin . '.' . $name;
        }

        return $res;
    }
}
