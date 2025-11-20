<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Piazzola;
use Illuminate\Console\Command;

class LoadClienti extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:load-clienti';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cliente::create(['nome' => 'Ambrosio Express di Ambrosio Christian']);
        Cliente::create(['nome' => 'Albanese Michele']);
        Cliente::create(['nome' => 'Autotrasporti Soa di Notaro Orlando']);
        Cliente::create(['nome' => 'GM Driver car di Lazzarin Andrea']);
        Cliente::create(['nome' => 'Assumma Maria']);
        Cliente::create(['nome' => 'Bordiga Giampietro']);
        Cliente::create(['nome' => 'Bragato Giuliano']);
        Cliente::create(['nome' => 'Calzetta Caterina']);
        Cliente::create(['nome' => 'Camper Sernice di parlato Aniello']);
        Cliente::create(['nome' => 'Camporeale Giuseppe']);
        Cliente::create(['nome' => 'Deligt srl']);
        Cliente::create(['nome' => 'Caracciolo Fabrizio']);
        Cliente::create(['nome' => 'Carbone Francesco']);
        Cliente::create(['nome' => 'Carulli Giuseppe']);
        Cliente::create(['nome' => 'Cenna Stefano']);
        Cliente::create(['nome' => 'Ciuhat Ionel Leon']);
        Cliente::create(['nome' => 'Colangelo Gianni']);
        Cliente::create(['nome' => 'Corsi Stefano']);
        Cliente::create(['nome' => 'Curti Furio Giuseppe']);
        Cliente::create(['nome' => 'D\'Adamo Nicolino']);
        Cliente::create(['nome' => 'De Melgazzi Marcello']);
        Cliente::create(['nome' => 'Barba Giuseppe']);
        Cliente::create(['nome' => 'Degani Tizziano']);
        Cliente::create(['nome' => 'Dematech srl De Matteis Pierangelo']);
        Cliente::create(['nome' => 'Diciaula Giancarlo']);
        Cliente::create(['nome' => 'Enrico Fabbrica']);
        Cliente::create(['nome' => 'Facchini Renato']);
        Cliente::create(['nome' => 'Ferrari Massimo']);
        Cliente::create(['nome' => 'Sabre srl']);
        Cliente::create(['nome' => 'Francesco Leopizzi']);
        Cliente::create(['nome' => 'Franco Antonella']);
        Cliente::create(['nome' => 'Galvan Luca']);
        Cliente::create(['nome' => 'Cerabino Michele Moglie']);
        Cliente::create(['nome' => 'Gessolo Massimo']);
        Cliente::create(['nome' => 'Gorgoglione Andrea Terrone Cristina']);
        Cliente::create(['nome' => 'Guarducci Massimo']);
        Cliente::create(['nome' => 'Imparato Paola']);
        Cliente::create(['nome' => 'Leoni Andrea']);
        Cliente::create(['nome' => 'Lorenzetti Gian Matteo']);
        Cliente::create(['nome' => 'Mainiero Claudio']);
        Cliente::create(['nome' => 'Maraboli Stefano']);
        Cliente::create(['nome' => 'Marco Marchi']);
        Cliente::create(['nome' => 'Galvan Luca Gianni']);
        Cliente::create(['nome' => 'Meroni Riccardo']);
        Cliente::create(['nome' => 'Michele Cerabino']);
        Cliente::create(['nome' => 'Minervini Corrado']);
        Cliente::create(['nome' => 'Mocanu Mariano Starcom s.r.l']);
        Cliente::create(['nome' => 'Monfredini Andrea Simona']);
        Cliente::create(['nome' => 'Monopoli Riccardo Monopoli Simone']);
        Cliente::create(['nome' => 'Pestoni Giorgio']);
        Cliente::create(['nome' => 'Pria Tiziano']);
        Cliente::create(['nome' => 'Pusterla Lara ( Franca Foppoli)']);
        Cliente::create(['nome' => 'Rebuzzi Mirko']);
        Cliente::create(['nome' => 'Renna Stefano']);
        Cliente::create(['nome' => 'Roberti Luca']);
        Cliente::create(['nome' => 'Rossi Marco']);
        Cliente::create(['nome' => 'Rossi Maurizio']);
        Cliente::create(['nome' => 'Sartorelli Paola']);
        Cliente::create(['nome' => 'Satariano Giuseppe']);
        Cliente::create(['nome' => 'Sem Carlo']);
        Cliente::create(['nome' => 'Baisol Soliani Christian']);
        Cliente::create(['nome' => 'Trabacchi Mauro']);
        Cliente::create(['nome' => 'Trisolini Alfredo']);
        Cliente::create(['nome' => 'Vighi Ennio']);
        Cliente::create(['nome' => 'Catia Moratto']);
        Cliente::create(['nome' => 'Centro Revisioni Espinasse Srl Borsani']);
    }
}
