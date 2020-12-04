#!/bin/env php
<?php
// phpcbf 
class SonarSearch
{
    protected $basicUrl = "https://sonarqube.good-god.com";
    protected $apiLink = [
        "search_projects" => "/api/components/search_projects",
        "search_history"  => "/api/measures/search_history"
    ];
    public $projects = [];

    public function setBasicURL($url)
    {
        $this->basicUrl = $url;
    }

    public function getAllProjects()
    {
        if ($this->projects) {
            return $this->projects;
        }
        $content = file_get_contents("{$this->basicUrl}{$this->apiLink['search_projects']}");
        $data = json_decode($content, true);
        $this->projects = array_column($data['components'], 'name');
        return $this->projects;
    }

    public function getProjectHistory(
        $project,
        $metrics = ["tests", "bugs", "coverage", "sqale_debt_ratio", "duplicated_lines_density", "vulnerabilities"],
        $ps = 1000 // page size
    ) {
        if (is_array($metrics)) {
            $metrics = implode(",", $metrics);
        }

        $query = http_build_query(
            [
            "component" => $project,
            "metrics" => $metrics,
            "ps" => $ps
            ]
        );

        $content = file_get_contents("{$this->basicUrl}{$this->apiLink['search_history']}?{$query}");
        return json_decode($content, true);
    }

    public function weekOfYearFilter($projectData)
    {
        $result = [];
        foreach($projectData['measures'] as $measures) {
            $firstWeek = 0;
            $metric = $measures['metric'];

            foreach($measures['history'] as $history) {
                if (!isset($history['value'])) {
                    continue;
                }
                $week = date('W', strtotime($history['date']));
                if ($firstWeek == 0) {
                    $firstWeek = $week;
                }
                $result[$metric][$week] = $history['value'];
            }

            // 補日期中間缺少的資料
            if ($firstWeek && $firstWeek != $week) {
                for ($i = $firstWeek; $i<$week; $i++) {
                    if (isset($result[$metric][$i])) {
                        $value = $result[$metric][$i];
                    } else {
                        $result[$metric][$i] = $value;
                    }
                }
                ksort($result[$metric]);
            }
        }
        return $result;
    }
}

$obj = new SonarSearch();
$projects = $obj->getAllProjects();
$report = [];
$metrics = ["tests", "bugs", "coverage", "sqale_debt_ratio", "duplicated_lines_density", "vulnerabilities"];

foreach($projects as $project) {
    $projectData = $obj->getProjectHistory($project, $metrics);
    $report[$project] = $obj->weekOfYearFilter($projectData);
}

foreach($metrics as $metric) {
    foreach($projects as $project) {
        if (!isset($report[$project][$metric])) {
            continue;
        }
        foreach ($report[$project][$metric] as $weekYear => $value) {
        }
    }
}

// $out = fopen('php://output', 'w');
// fputcsv($out, array('this','is some', 'csv "stuff", you know.'));
// fclose($out);

