#!/bin/sh

SED=/bin/sed
PHP=/usr/bin/php
SQLITE3=/usr/syno/bin/sqlite3
PHP_EXT_DIR=/lib/php/extensions
SZF_SEARCH_PHP=/usr/syno/synoman/webman/modules/DownloadStation/dlm/btsearch/btsearch.php
OPEN_BASEDIR=`pwd`:/tmp:/usr/syno/synoman/webman/modules/DownloadStation/dlm/btsearch:/usr/syno/etc/download
SZF_DB_PATH=db_result.db
SEARCH_MODULE=hliang

COMMON_PARAMETER="
        -d display_errors=On
        -d extension_dir=$PHP_EXT_DIR
        -d extension=mbstring.so
        -d extension=syno_compiler.so
        -d extension=curl.so
"
#         -d error_reporting=E_ERROR
#         -d error_reporting=E_ALL

SEARCH_PARAMETER="
        -d open_basedir=$OPEN_BASEDIR
"


Usage()
{
        cat >&2 <<EOF
Usage:
        list [-v]
                list all search module information
        search [query string]
                search for query string
EOF
}

##
# List plugin info
#
# @parameter Verbose: default is 0
#
##
List()
{
        local verbose=0
        if [ "$1" = "-v" ]; then
                verbose=1
        fi

        local cmd="$PHP -n $COMMON_PARAMETER -d safe_mode_exec_dir=/usr/syno/bin $SZF_SEARCH_PHP -p"
        local jsonList=$($cmd)

        escapedJsonList=$(echo "$jsonList" | $SED 's,",\\",g')

        local phpSrc="
                \$json = json_decode(\"$escapedJsonList\");
                if (\$json)  {
                        printf(\"%10s\\n\", 'name');
                        foreach (\$json as \$item) {
                                printf(\"%10s\\n\", \$item->name);
                        }
                } else {
                        printf(\"Failed to decode json list\\n\");
                        echo \$jsonList;
                }
        "

        if [ $verbose = 1 ]; then
                $PHP -r "print_r(json_decode(\"$escapedJsonList\"));"
        else
                $PHP -r "$phpSrc"
        fi
}

Search()
{
        local query="$1"

        if [ -z "$query" ]; then
                Usage;
                return 1;
        fi

        echo Query string: "$query"

        # -s: query string
        # -o: result db path
        # -e: plugin list string

        local cmd="$PHP -n $COMMON_PARAMETER $SEARCH_PARAMETER -d safe_mode_exec_dir=/usr/syno/bin $SZF_SEARCH_PHP -e $SEARCH_MODULE -s $query -o $SZF_DB_PATH"

        echo == cmd ==
        echo $cmd
        echo == cmd ==

        if [ -f $SZF_DB_PATH ]; then
            echo found db "$SZF_DB_PATH" exists, remove it
            rm $SZF_DB_PATH
        fi

        # execute cmd
        echo is searching...
        returnStr=$($cmd)
        echo resturn str: $returnStr

        # sqlite3 db_result.db ".schema"
        #
        # sqlite3 -column db_result.db "select title, seeds, dlurl from search_results"
        local SQL="SELECT title, seeds, dlurl FROM search_results ORDER BY seeds DESC LIMIT 10"

        $SQLITE3 -column $SZF_DB_PATH "$SQL"
}


case $1 in
h|help|-h|--help)
    Usage;
    return 1;
    ;;
list|-l|--list)
    List $2 $3;
    return 0;
    ;;
search|--search)
        Search $2 $3;
        return 0;
        ;;
*)
    Usage;
    return 1;
    ;;
esac

# vim: set expandtab ff=unix ts=4 

