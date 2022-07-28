'use strict';

let router ;
let app ;
let wd = new WikiData() ;
let internal_user_id = 0 ;

$(document).ready ( function () {
    vue_components.toolname = 'quest' ;
    Promise.all ( [
        vue_components.loadComponents ( ['wd-date','wd-link','tool-translate','tool-navbar','commons-thumbnail','widar','autodesc','typeahead-search','value-validator',
            'vue_components/command.html',
            'vue_components/main-page.html',
            'vue_components/queries-page.html',
            ] )
    ] )
    .then ( () => {
        widar_api_url = 'https://quest.toolforge.org/api.php' ;
        const routes = [
            { path: '/queries', component: QueriesPage , props:true },
            { path: '/queries/:user_name', component: QueriesPage , props:true },
            { path: '/', component: MainPage , props:true },
            { path: '/:query_id', component: MainPage , props:true },
        ] ;
        $.get ( './api.php?action=get_current_user_id' , function ( d ) {
            internal_user_id = d.user_id ;
            router = new VueRouter({routes}) ;
            app = new Vue ( { router } ) .$mount('#app') ;
        } ) ;
    } ) ;
} ) ;
