cakephp.datasource.hyperestraier
================================

CakePHP Darasource for Hyper Estraier


Recommended environment
----------------------------------------------------------------------
* [CakePHP 2.2.1](http://github.com/cakephp/cakephp/zipball/2.2.1)  
* [Hyper Estraier 1.4.13](http://fallabs.com/hyperestraier/hyperestraier-1.4.13.tar.gz)  
* [Services_HyperEstraier 0.6.0](https://github.com/rsky/Services_HyperEstraier)  

How to use
----------------------------------------------------------------------
As a prerequisite, CakePHP, Service_HyperEstraier and Hyper Estraier setting has been completed.

1. Under '/app/Lib' directory, copy 'Service_Hyperestraier' library.
2. Under '/app/Model/Datasource' directory, copy this datasource file (Hyperestraier.php.
3. Describe the contents of the following in database.php  
<pre> 
    public $he = array(
        'datasource' => 'Hyperestraier',
        'host'  => 'localhost',
        'port'  => 1978,
        'node'  => 'nodename',
        'login' => 'admin',
        'password' => 'admin'
    );   
</pre>
4. Create model file and describe the contents of the following this.
public $useDbConfig = 'he';

Related information
----------------------------------------------------------------------
[CakePHP + Hyper Estraierで全文検索 - Datasourceを作ってみる - ミラボ](http://log.miraoto.com/2012/10/682/ "Hyperestraier Datasource")

License
----------------------------------------------------------------------
Copyright (c) 2012 miraoto
MIT License (http://www.opensource.org/licenses/mit-license.php)

