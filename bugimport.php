<?php
// Include Library classes
require_once(dirname(__FILE__) . 'bugherd/lib/BugHerd/Project.php');
require_once(dirname(__FILE__) . 'bugherd/lib/BugHerd/Task.php');
require_once(dirname(__FILE__) . 'bugherd/lib/BugHerd/User.php');
require_once(dirname(__FILE__) . 'bugherd/lib/BugHerd/Comment.php');
require_once(dirname(__FILE__) . 'bugherd/lib/BugHerd/Exception.php');
require_once(dirname(__FILE__) . 'bugherd/lib/BugHerd/Api.php');

/**
 * class to import bugs from bugherd to bugzilla
 * Copyright (C) 2012 rojansinha@gmail.com 
 * This program comes with ABSOLUTELY NO WARRANTY;
 * 
 **/
class ImportBugs
{
    private $buzilla_url;
    private $buzilla_login;
    private $buzilla_password;

    function __construct($bugzilla_url = NULL, $bugzilla_login = NULL, $bugzilla_password = NULL)
    {
        // bugzilla xmlrpc url
        $this->bugzilla_url = 'http://localhost/bugzilla/xmlrpc.cgi';
        // bugzilla username
        $this->bugzilla_login= 'username@bugzilla.com';
        // bugzilla password
       $this->bugzilla_password = 'password';
    }

    /*
     * This is the main and only function
     * which does all the importing job
     */
    function import()
    {
        $api = new BugHerd_Api('bugherd@username.com', 'bugherdpassword');
        //print_r ($api->ListProjects);

        try
        {
            echo "Fetching task list\n";

            // Change project_id with real project id
            $task_list = $api->listTasks('project_id');
            //print_r ($task_list);
        }

        catch (Exception $e)
        {
            // if can not fetch task list print error and die;
            print_r ($e);
            die();
        }

        /*
         * Bugheard and bugzilla priority maping
         * change this according to your need
         */
        $priority = array(
            '0' => '---',
            '1' => 'Highest',
            '2' => 'High',
            '3' => 'Normal',
            '4' => 'Low'
        );

        /*
         * Bugheard and bugzilla status maping
         * change this according to your need
         */
        $status = array(
            '0' => 'CONFIRMED',
            '1' => 'CONFIRMED',
            '2' => 'IN_PROGRESS',
            '3' => 'RESOLVED',
            '4' => 'RESOLVED',
        );

        // login to bugzilla using bz_webservice_demo.pl perl script. This script is provided in bugzilla/contrib/bz_webservice_demo.pl
        $login_to_bugzilla = exec('./bz_webservice_demo.pl --uri '
            . $this->bugzilla_url .' --login '
            . $this->bugzilla_login .' --password '
            . $this->bugzilla_password);

        echo "loging into bugzilla\n".$login_to_bugzilla."\n";

        if ($login_to_bugzilla === 'Login successful.')
        {
            //echo "good";
            foreach ($task_list as $task)
            {
                $bugzilla_priority = $priority[$task->priority_id];
                $bugzilla_status = $status[$task->status_id];
                $fp = fopen('bugs/'.$task->local_id, 'w+');
                $bugherd_file = 'bugs/'. $task->local_id;

                $total_words = explode(' ', $task->description);

                // trim very long description to make summary. Bugheard has no bug summary feature
                if ($total_words > 5)
                {
                    $bug_summary = implode(' ', array_slice($total_words, 0, 5)). ".....";
                }
                else
                {
                    $bug_summary = $task->description;
                }

                // escaping '@' character. bz_weservice_demo.pl throws error if not escaped.
                // there should be more such characters which needs to be escape but so far i
                // found only this.
                $bug_description = str_replace('@', '\@', $task->description);

                $file_content = <<<BUG
{
    product => "TG",
    component => "BUGHERD",
    summary => "$bug_summary",
    version => "unspecified",
    description => "$bug_description",
    op_sys => "All",
    platform => "All",
    priority => "$bugzilla_priority",
    severity => "normal",
    status  => "$bugzilla_status",
};
BUG;
                fwrite($fp, $file_content);
                fclose($fp);

                // import bug to bugzilla
                $get_bug_id = exec(
                    './bz_webservice_demo.pl --uri '
                    . $this->bugzilla_url .' --create '
                    . $bugherd_file
                );

                if ($get_bug_id)
                {
                    echo "Bug - $get_bug_id imported \n";
                }
                else
                {
                    echo "could not import bug";
                }
                //break;
            }
        }
    }

}

?>
