Elastica 6.2.1 для Elasticsearch 5.6 и PHP 8.4 / 8.5
===================================================

Форк [ruflin/Elastica](https://github.com/ruflin/Elastica) 6.2.1 (последний релиз ветки 6.x), доработанный
для работы с **Elasticsearch 5.6** на **PHP 7.1 – 8.5**. Официально 6.x поддерживает только Elasticsearch 6.x,
а ветка 5.x (последний релиз 5.3.6) не работает на PHP 8.

| Что                           | Версии                                               |
| ----------------------------- | ---------------------------------------------------- |
| Elasticsearch                 | 5.6.x (тесты на 5.6.16)                              |
| PHP                           | ^7.1 \|\| ^8.0 (тесты на 7.4, 8.4 и 8.5)             |
| elasticsearch/elasticsearch   | ^6.0 (6.8.4 подходит без изменений)                  |
| psr/log                       | 1, 2 или 3                                           |
| guzzlehttp/guzzle             | 6 или 7 (нужен только для транспортов Guzzle и AwsAuthV4) |

Установка:

```bash
composer require overherz/elastic5_6_16_php8:^1.0
```

Пакет объявляет `replace` для `ruflin/elastica` 6.2.1. Поэтому вместе с оригинальной Elastica его не поставить: у них
общий namespace `Elastica\`. Пакеты, которым нужен `ruflin/elastica` 6.x, получат этот форк.


Отличия от upstream 6.2.1
-------------------------

**PHP 8.4 и 8.5, psr/log, Guzzle 7**

* Все параметры вида `Type $x = null` объявлены как `?Type $x = null` (в PHP 8.4 неявный nullable deprecated).
  Для PHP 8.5 отдельных правок не понадобилось: библиотека и тесты работают на нём без deprecation.
* Удалён `Elastica\Query\Match`: на PHP 8 этот файл не парсится (`match` — ключевое слово). Используйте `Elastica\Query\MatchQuery`.
* `Elastica\Log::log()` объявлен с `: void`, `psr/log` разрешён `^1.0 || ^2.0 || ^3.0` (Laravel 10+ тянет monolog 3 и psr/log 2/3).
* Транспорты `Guzzle` и `AwsAuthV4` работают с Guzzle 7 и guzzlehttp/psr7 2. В upstream 6.2.1 с ними любой запрос с телом
  падал (`Psr7\stream_for()` удалён), а ответ 4xx/5xx становился ошибкой соединения: Elastica помечала соединение мёртвым,
  и следующий запрос падал с `No enabled connection`. Теперь используется `http_errors`, `Psr7\Utils::streamFor()`
  и `HandlerStack::create()`, добавлен конфликт с `guzzlehttp/psr7 <1.7`.
* `"php": "^7.1||^8.0"` вместо `^7.0||^8.0`: nullable-типы и `void` требуют PHP 7.1.

**Elasticsearch 5.6**

* Возвращён `Elastica\Query\Percolate::setDocumentType()`: в 5.6 параметр `document_type` обязателен.
* В докблоках отмечено, что `FunctionScore::addRandomScoreFunction()` с `$field` — это 6.0+, а
  `Suggest\Term::setStringDistanceAlgorithm()` в 5.6 принимает `levenstein` и `jarowinkler`.
* Тесты и CI переведены на Elasticsearch 5.6.16: тип `doc` вместо `_doc` (5.6 не разрешает типы с `_`), индексы с join-полем
  создаются с `index.mapping.single_type: true`, ожидания поведения сервера ES 6 заменены поведением 5.6, `docker/` поднимает
  кластер 5.6.16 из двух узлов с ingest-attachment.


Переход с Elastica 5.3.6
------------------------

Ниже всё, что меняется для кода, написанного под 5.3.6. Список собран полным сравнением публичного API обеих версий,
прогоном тестов 5.3.6 против этой версии и сравнением кода библиотек без комментариев.

### Падает сразу при вызове

| Было в 5.3.6                                                   | Что использовать                                                                             |
| -------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| `Index::analyze($text, $args)`                                 | `Index::analyze(['text' => $text] + $args)`, параметры анализа передаются в теле             |
| `Query\Match`                                                  | `Query\MatchQuery`                                                                           |
| `Query\MatchQuery::setFieldType($field, $type)`                | `Query\MatchPhrase`, `Query\MatchPhrasePrefix`                                               |
| `new Query\Ids($type, $ids)`, `Ids::setType()`, `Ids::addType()` | `new Query\Ids($ids)`; тип — через поиск по типу (`Search::addType()`, `Type::search()`) или `$ids->setParam('type', $types)` |
| `QueryBuilder\DSL\Query::ids($type, $ids)`                     | `ids($ids)`                                                                                  |
| `Query\BoolQuery::setMinimumNumberShouldMatch()`               | `setMinimumShouldMatch()`                                                                    |
| `Query\ConstantScore::setQuery()`                              | `setFilter()`                                                                                |
| `Query\Common::setDisableCoord()`                              | `$query->setParam('disable_coord', true)`                                                    |
| `Query\GeoDistance::setOptimizeBbox()`, константы `OPTIMIZE_BBOX_*` | `$query->setParam('optimize_bbox', 'memory')`                                           |
| `GeoDistance::DISTANCE_TYPE_SLOPPY_ARC` (запрос и агрегация)   | строка `'sloppy_arc'` или `DISTANCE_TYPE_ARC` / `DISTANCE_TYPE_PLANE`                         |
| `Query\GeoDistanceRange`                                       | `Query\GeoDistance` (внешний радиус) в bool-запросе с `must_not` для внутреннего               |
| `Query\Indices`                                                | `Query\Term` по полю `_index` внутри bool-запроса                                            |
| `Query\Builder`                                                | объекты `Query\*` или `QueryBuilder`                                                         |
| `Script\ScriptFile`                                            | stored script (`PUT _scripts/<id>`) и `Script\ScriptId`, либо `Script\Script`                |
| константы `LANG_GROOVY`, `LANG_JS`, `LANG_MVEL`, `LANG_NATIVE`, `LANG_PYTHON` | строки `'groovy'` и т.д.                                                      |
| `Type\Mapping::setParent($type)`                               | `$mapping->setParam('_parent', ['type' => $type])`                                           |
| `Type\Mapping::enableAllField($enabled)`, `setAllField($params)` | `$mapping->setParam('_all', ['enabled' => $enabled])`                                      |
| `Index::optimize()`                                            | `Index::forcemerge()`                                                                        |
| `Tool\CrossIndex`                                              | `Elastica\Reindex`                                                                           |
| `Script::create(['script' => ['inline' => …]])`                | ключ `source` вместо `inline`; ключ `file` больше не поддерживается                          |
| `Aggregation\Percentiles::addPercent()`, `setCompression()` с нечисловым аргументом | только `float` (типизированные параметры)                               |
| наследники `Transport\Guzzle` с `_getGuzzleClient($baseUrl, $persistent)` | сигнатура `_getGuzzleClient($persistent)`, базовый URL передаётся в опциях запроса |

### Работает иначе, без ошибки

* **Скрипты** сериализуются с ключом `source` вместо `inline`. Elasticsearch 5.6 понимает оба варианта (на `inline` пишет
  deprecation в лог), меняется только вывод `toArray()`.
* **`ResultSet::next()`** ничего не возвращает (было `Result|false`), а `current()` можно вызывать только когда
  `valid()` вернул `true`. То же для `Bulk\ResponseSet` и `Multi\ResultSet`. `foreach` и `getResults()` работают как раньше.
* **`Util::escapeTerm()`** удаляет символы `<` и `>` вместо экранирования: в `query_string` их экранировать нельзя.
* **`Aggregation\Percentiles::setCompression()`** отправляет `tdigest.compression`. В 5.3.6 отправлялся `compression`
  верхнего уровня, который Elasticsearch 5.6 отвергает, то есть раньше этот метод с 5.6 не работал.
* **`Scroll`** в конце обхода сам закрывает scroll-контекст (`DELETE _search/scroll`).
* **`Index::refresh()`** использует `POST` вместо `GET`.
* **Булевы параметры URL** отправляются как `true`/`false`, а не `1`/`0`.
* **`Document::setDocAsUpsert()`** приводит значение к `bool`.
* `Client::addDocuments()`, `updateDocuments()`, `deleteDocuments()` принимают вторым аргументом параметры запроса.
  Например, `$client->addDocuments($docs, ['refresh' => 'wait_for'])`. Параметр документа `_refresh`
  (`$document->setParam('_refresh', …)`) в bulk-запросе игнорируется — и в 5.3.6, и здесь.

### Чего в Elasticsearch 5.6 нет, хотя API 6.x это позволяет

* `Aggregation\WeightedAvg` — появилась в Elasticsearch 6.4.
* `random_score` с `field` (`addRandomScoreFunction(..., $field)`) — Elasticsearch 6.0+.
* Сортировка terms-агрегации по `_key` — в 5.6 это `_term`.
* `Percolate` без `setDocumentType()` — 5.6 требует `document_type`.
* Join-поле работает только в индексе, созданном с `index.mapping.single_type: true`. В индексах с несколькими типами
  связь родитель/потомок задаётся через `_parent`.
* Имена ошибок и поведение сервера — как в 5.6: `index_already_exists_exception` (а не `resource_already_exists_exception`),
  удаление индекса через алиас разрешено, числовые границы `date_range` всегда epoch_millis.


Тесты
-----

Тест-сьют upstream, адаптированный под 5.6, проходит на Elasticsearch 5.6.16 (2 узла, ingest-attachment) на PHP 7.4,
8.4 и 8.5: unit — 521 тест, functional — 451 тест, 2 пропущены (`weighted_avg` требует 6.4, ещё один пропущен
и в upstream из-за бага Elasticsearch). Транспорт Guzzle проверен с Guzzle 6.5 и 7.

```bash
docker compose --file=docker/docker-compose.proxy.yaml --file=docker/docker-compose.es.yaml up --detach
composer update
vendor/bin/phpunit -c test/ --group unit
ELASTICSEARCH_VERSION=5.6.16 vendor/bin/phpunit -c test/ --group functional
```

---

Ниже — оригинальный README Elastica 6.2.1.

Elastica: elasticsearch PHP Client
==================================

[![Latest Stable Version](https://poser.pugx.org/ruflin/Elastica/v/stable.png)](https://packagist.org/packages/ruflin/elastica)
[![Build Status](https://github.com/ruflin/Elastica/workflows/Continuous%20integration/badge.svg?branch=6.x)](https://github.com/ruflin/Elastica/actions?query=workflow%3A%22Continuous%20integration%22%20branch%3A6.x)
[![codecov.io](http://codecov.io/github/ruflin/Elastica/coverage.svg?branch=master)](http://codecov.io/github/ruflin/Elastica?branch=master)
[![Dependency Status](https://www.versioneye.com/php/ruflin:elastica/dev-master/badge.svg)](https://www.versioneye.com/php/ruflin:elastica/dev-master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ruflin/Elastica/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/ruflin/Elastica/?branch=master)
[![Total Downloads](https://poser.pugx.org/ruflin/Elastica/downloads.png)](https://packagist.org/packages/ruflin/elastica)
[![Join the chat at https://gitter.im/ruflin/Elastica](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/ruflin/Elastica?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

All documentation for Elastica can be found under [Elastica.io](http://Elastica.io/).
If you have questions, don't hesitate to ask them on [Stackoverflow](http://stackoverflow.com/questions/tagged/elastica) and add the Tag "Elastica" or
in our [Gitter](https://gitter.im/ruflin/Elastica) channel.
All library issues should go to the [issue tracker from github](https://github.com/ruflin/Elastica/issues).


Compatibility
-------------
This release is compatible with all Elasticsearch 6.x releases. It was tested with version 6.5.2.


Contributing
------------
Contributions are always welcome. For details on how to contribute, check the [CONTRIBUTING](https://github.com/ruflin/Elastica/blob/master/CONTRIBUTING.md) file.


Versions & Dependencies
------------
This project tries to follow Elasticsearch in terms of [End of Life](https://www.elastic.co/support/eol) and maintenance since 5.x.
It is generally recommended to use the latest point release of the relevant branch.

| Elastica branch                                       | ElasticSearch | elasticsearch-php | PHP            |
| ----------------------------------------------------- | ------------- | ----------------- | -------------- |
| [7.x](https://github.com/ruflin/Elastica/tree/master) | 7.x           | ^7.0              | ^7.2 \|\| ^8.0 |
| [6.x](https://github.com/ruflin/Elastica/tree/6.x)    | 6.x           | ^6.0              | ^7.0 \|\| ^8.0 |

Unmaintained versions:

| Elastica version                                      | ElasticSearch | elasticsearch-php | PHP            |
| ----------------------------------------------------- | ------------- | ----------------- | -------------- |
| [5.x](https://github.com/ruflin/Elastica/tree/5.x)    | 5.x           | ^5.0              | \>=5.6         |
| [3.x](https://github.com/ruflin/Elastica/tree/3.x)    | 2.4.0         | no                | \>=5.4         |
| [2.x](https://github.com/ruflin/Elastica/tree/2.x)    | 1.7.2         | no                | \>=5.3.3       |
------------
