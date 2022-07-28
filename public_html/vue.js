'use strict';

let router ;
let app ;
let wd = new WikiData() ;

let subjects = {} ;

$(document).ready ( function () {
//https://quest.toolforge.org/api.php?action=authorize
    vue_components.toolname = 'quest' ;
    Promise.all ( [
        vue_components.loadComponents ( ['wd-date','wd-link','tool-translate','tool-navbar','commons-thumbnail','widar','autodesc','typeahead-search','value-validator',
            'vue_components/command.html',
            'vue_components/main-page.html',
            //'vue_components/subject-page.html',
            //'vue_components/shex-page.html',
            ] )
    ] )
    .then ( () => {
        widar_api_url = 'https://quest.toolforge.org/api.php' ;

        wd.set_custom_api ( 'https://www.wikidata.org/w/api.php' , function () {
            wd_link_wd = wd ;
              const routes = [
                { path: '/', component: MainPage , props:true },
                //{ path: '/random_command', component: RandomCommand , props:true },
                /*
                { path: '/subject/:subject/:q', component: SubjectPage , props:true },
                { path: '/shex/:e', component: ShexPage , props:true },
                */
              ] ;
              router = new VueRouter({routes}) ;
              app = new Vue ( { router } ) .$mount('#app') ;
              //$('#help_page').attr('href',wd.page_path.replace(/\$1/,config.source_page));
        } ) ;

    } ) ;
} ) ;
