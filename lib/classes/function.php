<?php
function fission_page($current_page, $count_all,$limit_record,$previous,$next,$class="pagers") {
    $current_page =(int)$current_page;
    $count_all =(int)$count_all;
	$ech_pr = '';
    if (@ceil($count_all / $limit_record) > 1 AND $limit_record != 0) {
        $ech_pr = "<div class='title'><span>Страницы:</span></div><div class='pages'>";
        $all_p = ceil($count_all / $limit_record);
        for ($isa = 1; $isa <= $all_p; $isa++) {
            $sdvig_1 = 3;
            $sdvig_2 = 3;
            if ($current_page <=  6) $sdvig_1 = 7 AND $sdvig_2 = 4;
            if ($all_p - $current_page <=  5) $sdvig_1 = 4 AND $sdvig_2 = 7;
            if (($isa < $current_page + 3 AND $isa > $current_page - 3) OR ($isa <= $sdvig_1) OR ($isa > $all_p - $sdvig_2)) {
                if ($isa != $current_page) {
                    $ech_pr = $ech_pr.'<a class="'.$class.'" data-page="'.$isa.'" href="#">'.$isa.'</a>';
                    $krapki = 1;
                } else {
                    $ech_pr = $ech_pr.'<a class="'.$class.' -active" data-page="'.$isa.'" href="#">'.$isa.'</a>';                  
                    $krapki = 1;
                }
            } else {
                if ($krapki == 1) {
                    $ech_pr = $ech_pr."<em>";
                    if ($current_page < 6 OR $all_p - $current_page < 6 ) {
                        $ech_pr = $ech_pr."..........";
                    } else {
                        $ech_pr = $ech_pr.".....";
                    }

                    $ech_pr = $ech_pr."</em>";
                    $krapki = 0;
                }
            }
        }

        $p_n=$current_page+1;
        $p_p=$current_page-1;

        if ($current_page > 1) {
            $ech_pr = $ech_pr.'<a class="'.$class.'" data-page="'.$p_p.'" href="#">'.$previous.'</a>';
        } else {
            $ech_pr = $ech_pr."<span class='prev'>&nbsp;$previous&nbsp;</span>";
        }
        if ($current_page < ceil($count_all / $limit_record)) {
            $ech_pr = $ech_pr.'<a class="'.$class.'" data-page="'.$p_n.'" href="#">'.$next.'</a>';
        } else {
            $ech_pr = $ech_pr."<span class='next'>&nbsp;$next&nbsp;</span>";
        }
      $ech_pr = $ech_pr."</div>";
    }
    return "&nbsp;".$ech_pr."&nbsp;";
}