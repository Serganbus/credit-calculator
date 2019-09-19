<?

/* # Требует подключеного класса MySql
 */

class page {

    public $page = array (
        'domain' => 'NULL', // имя домена
        'url' => 'NULL', // url адрес страницы
        'listPage' => 'NULL', // при постраничном выводе номер страницы
        'id' => 'NULL', // id страницы
        'pid' => 'NULL', // id родителя раздела
        'name' => 'NULL', // имя раздела
        'link' => 'NULL', // латинское имя страницы
        'off' => 'NULL', // 1 - страница выключена, 0 - включена
        'can_del' => 'NULL', // 1 - страницу можно удалить, 0 - нельзя ( используется только в админке )
        'range' => 'NULL', // ранг раздела
        'content' => 'NULL', // контент страницы
        'title' => 'NULL', // тег h1 страницы
        'meta_title' => 'NULL', // тег title
        'meta_description' => 'NULL', // тег description
        'meta_keywords' => 'NULL', // тег keywords
        'extra_meta' => 'NULL', // дополнительные метаданные раздела
        'meta_lang' => 'NULL', // тег language
        'template' => 'NULL', // шаблон страницы
        'target' => 'NULL', // _self, _blank,  _parent,  _top
        'edit_date' => 'NULL', // дата последнего редактирования раздела
        'create_date' => 'NULL', // дата создания раздела
        'in_menu' => 'NULL', // 1 - раздел отображается в главном меню, 0 - не отображается
        'views' => 'NULL', // количество просмотров страницы ( увеличивается автоматически )
    );
    private $root = '';
    private $url = '';

    public function __construct() {
        $this->root = 'http://' . $_SERVER['HTTP_HOST'];
        $this->page['domain'] = $_SERVER['HTTP_HOST'];
    }

    /* грузит информацию о текущей странице в массив "page" */
    public function LoadPage($id = 0) {
        global $db;
        global $_GET;
        
        if (isset($_GET['id']) && (int) !empty($_GET['id'])) {
            if ($id === 0)
                $id = $_GET['id'];
            if (!empty($id)) {
                /* грузим все данные о странице в массив "page" свойства объекта */
                $t = $db->GetTable("SELECT * FROM `parts` WHERE `id`=%s LIMIT 1 ", array($_GET['id']));
                if ($t != NULL && count($t) > 0) {
                    foreach ($t as $r) {
                        $this->page['id'] = $r['id'];
                        $this->page['pid'] = $r['pid'];
                        $this->page['url'] = $r['url'];
                        $this->page['name'] = $r['name'];
                        $this->page['link'] = $r['link'];
                        $this->page['can_del'] = $r['can_del'];
                        $this->page['range'] = $r['range'];
                        $this->page['content'] = $r['content'];
                        $this->page['off'] = $r['off'];
                        $this->page['title'] = $r['title'];
                        $this->page['meta_title'] = $r['meta_title'];
                        $this->page['meta_description'] = $r['meta_description'];
                        $this->page['meta_keywords'] = $r['meta_keywords'];
                        $this->page['extra_meta'] = $r['extra_meta'];
                        $this->page['template'] = $r['template'];
                        $this->page['target'] = $r['target'];
                        $this->page['edit_date'] = $r['edit_date'];
                        $this->page['create_date'] = $r['create_date'];
                        $this->page['in_menu'] = $r['in_menu'];
                        $this->page['meta_lang'] = $r['meta_lang'];
                    }
                }
            }
        }
    }

    /* возвращает значение поля, переданного в качестве первого аргумента, по id страницы */
    public function returnById($field, $id) {
        global $db;
        $res = $db->GetRow("SELECT `%s` FROM `parts` WHERE `id`=%s LIMIT 1", array($field, $id));
        if ($res != NULL)
            return $res[0];
        else
            return '';
    }
}
?>