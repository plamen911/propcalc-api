<?php

namespace App\Command;

use App\Entity\Nationality;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-nationalities',
    description: 'Seeds the nationalities table with data',
)]
class SeedNationalityCommand extends Command
{
    private EntityManagerInterface $entityManager;

    // JSON data for nationalities
    private const NATIONALITY_DATA = [
        [
            "id" => 1,
            "name" => "България",
            "position" => 1
        ],
        [
            "id" => 2,
            "name" => "Австралия",
            "position" => 2
        ],
        [
            "id" => 3,
            "name" => "Австрия",
            "position" => 3
        ],
        [
            "id" => 4,
            "name" => "Азербайджан",
            "position" => 4
        ],
        [
            "id" => 5,
            "name" => "Албания",
            "position" => 5
        ],
        [
            "id" => 6,
            "name" => "Алжир",
            "position" => 6
        ],
        [
            "id" => 7,
            "name" => "Ангола",
            "position" => 7
        ],
        [
            "id" => 8,
            "name" => "Андора",
            "position" => 8
        ],
        [
            "id" => 9,
            "name" => "Антигуа и Барбуда",
            "position" => 9
        ],
        [
            "id" => 10,
            "name" => "Аржентина",
            "position" => 10
        ],
        [
            "id" => 11,
            "name" => "Армения",
            "position" => 11
        ],
        [
            "id" => 12,
            "name" => "Афганистан",
            "position" => 12
        ],
        [
            "id" => 13,
            "name" => "Бангладеш",
            "position" => 13
        ],
        [
            "id" => 14,
            "name" => "Барбадос",
            "position" => 14
        ],
        [
            "id" => 15,
            "name" => "Бахамски острови",
            "position" => 15
        ],
        [
            "id" => 16,
            "name" => "Бахрейн",
            "position" => 16
        ],
        [
            "id" => 17,
            "name" => "Беларус",
            "position" => 17
        ],
        [
            "id" => 18,
            "name" => "Белгия",
            "position" => 18
        ],
        [
            "id" => 19,
            "name" => "Белиз",
            "position" => 19
        ],
        [
            "id" => 20,
            "name" => "Бенин",
            "position" => 20
        ],
        [
            "id" => 21,
            "name" => "Боливия",
            "position" => 21
        ],
        [
            "id" => 22,
            "name" => "Босна и Херцеговина",
            "position" => 22
        ],
        [
            "id" => 23,
            "name" => "Ботсвана",
            "position" => 23
        ],
        [
            "id" => 24,
            "name" => "Бразилия",
            "position" => 24
        ],
        [
            "id" => 25,
            "name" => "Бруней",
            "position" => 25
        ],
        [
            "id" => 26,
            "name" => "Буркина Фасо",
            "position" => 26
        ],
        [
            "id" => 27,
            "name" => "Бурунди",
            "position" => 27
        ],
        [
            "id" => 28,
            "name" => "Бутан",
            "position" => 28
        ],
        [
            "id" => 29,
            "name" => "Вануату",
            "position" => 29
        ],
        [
            "id" => 30,
            "name" => "Ватикана",
            "position" => 30
        ],
        [
            "id" => 31,
            "name" => "Великобритания",
            "position" => 31
        ],
        [
            "id" => 32,
            "name" => "Венецуела",
            "position" => 32
        ],
        [
            "id" => 33,
            "name" => "Виетнам",
            "position" => 33
        ],
        [
            "id" => 34,
            "name" => "Габон",
            "position" => 34
        ],
        [
            "id" => 35,
            "name" => "Гамбия",
            "position" => 35
        ],
        [
            "id" => 36,
            "name" => "Гана",
            "position" => 36
        ],
        [
            "id" => 37,
            "name" => "Гватемала",
            "position" => 37
        ],
        [
            "id" => 38,
            "name" => "Гвинея",
            "position" => 38
        ],
        [
            "id" => 39,
            "name" => "Гвинея-Бисау",
            "position" => 39
        ],
        [
            "id" => 40,
            "name" => "Германия",
            "position" => 40
        ],
        [
            "id" => 41,
            "name" => "Гренада",
            "position" => 41
        ],
        [
            "id" => 42,
            "name" => "Грузия",
            "position" => 42
        ],
        [
            "id" => 43,
            "name" => "Гърция",
            "position" => 43
        ],
        [
            "id" => 44,
            "name" => "Дания",
            "position" => 44
        ],
        [
            "id" => 45,
            "name" => "Джибути",
            "position" => 45
        ],
        [
            "id" => 46,
            "name" => "Доминика",
            "position" => 46
        ],
        [
            "id" => 47,
            "name" => "Доминиканска република",
            "position" => 47
        ],
        [
            "id" => 48,
            "name" => "Египет",
            "position" => 48
        ],
        [
            "id" => 49,
            "name" => "Еквадор",
            "position" => 49
        ],
        [
            "id" => 50,
            "name" => "Екваториална Гвинея",
            "position" => 50
        ],
        [
            "id" => 51,
            "name" => "Еритрея",
            "position" => 51
        ],
        [
            "id" => 52,
            "name" => "Есватини",
            "position" => 52
        ],
        [
            "id" => 53,
            "name" => "Естония",
            "position" => 53
        ],
        [
            "id" => 54,
            "name" => "Етиопия",
            "position" => 54
        ],
        [
            "id" => 55,
            "name" => "Замбия",
            "position" => 55
        ],
        [
            "id" => 56,
            "name" => "Зимбабве",
            "position" => 56
        ],
        [
            "id" => 57,
            "name" => "Израел",
            "position" => 57
        ],
        [
            "id" => 58,
            "name" => "Индия",
            "position" => 58
        ],
        [
            "id" => 59,
            "name" => "Индонезия",
            "position" => 59
        ],
        [
            "id" => 60,
            "name" => "Ирак",
            "position" => 60
        ],
        [
            "id" => 61,
            "name" => "Иран",
            "position" => 61
        ],
        [
            "id" => 62,
            "name" => "Ирландия",
            "position" => 62
        ],
        [
            "id" => 63,
            "name" => "Исландия",
            "position" => 63
        ],
        [
            "id" => 64,
            "name" => "Испания",
            "position" => 64
        ],
        [
            "id" => 65,
            "name" => "Италия",
            "position" => 65
        ],
        [
            "id" => 66,
            "name" => "Йемен",
            "position" => 66
        ],
        [
            "id" => 67,
            "name" => "Йордания",
            "position" => 67
        ],
        [
            "id" => 68,
            "name" => "Кабо Верде",
            "position" => 68
        ],
        [
            "id" => 69,
            "name" => "Казахстан",
            "position" => 69
        ],
        [
            "id" => 70,
            "name" => "Камбоджа",
            "position" => 70
        ],
        [
            "id" => 71,
            "name" => "Камерун",
            "position" => 71
        ],
        [
            "id" => 72,
            "name" => "Канада",
            "position" => 72
        ],
        [
            "id" => 73,
            "name" => "Катар",
            "position" => 73
        ],
        [
            "id" => 74,
            "name" => "Кения",
            "position" => 74
        ],
        [
            "id" => 75,
            "name" => "Кипър",
            "position" => 75
        ],
        [
            "id" => 76,
            "name" => "Киргизстан",
            "position" => 76
        ],
        [
            "id" => 77,
            "name" => "Кирибати",
            "position" => 77
        ],
        [
            "id" => 78,
            "name" => "Китай",
            "position" => 78
        ],
        [
            "id" => 79,
            "name" => "Колумбия",
            "position" => 79
        ],
        [
            "id" => 80,
            "name" => "Коморски острови",
            "position" => 80
        ],
        [
            "id" => 81,
            "name" => "Конго",
            "position" => 81
        ],
        [
            "id" => 82,
            "name" => "Коста Рика",
            "position" => 82
        ],
        [
            "id" => 83,
            "name" => "Кот д'Ивоар",
            "position" => 83
        ],
        [
            "id" => 84,
            "name" => "Куба",
            "position" => 84
        ],
        [
            "id" => 85,
            "name" => "Кувейт",
            "position" => 85
        ],
        [
            "id" => 86,
            "name" => "Лаос",
            "position" => 86
        ],
        [
            "id" => 87,
            "name" => "Латвия",
            "position" => 87
        ],
        [
            "id" => 88,
            "name" => "Лесото",
            "position" => 88
        ],
        [
            "id" => 89,
            "name" => "Либерия",
            "position" => 89
        ],
        [
            "id" => 90,
            "name" => "Ливан",
            "position" => 90
        ],
        [
            "id" => 91,
            "name" => "Либия",
            "position" => 91
        ],
        [
            "id" => 92,
            "name" => "Литва",
            "position" => 92
        ],
        [
            "id" => 93,
            "name" => "Лихтенщайн",
            "position" => 93
        ],
        [
            "id" => 94,
            "name" => "Люксембург",
            "position" => 94
        ],
        [
            "id" => 95,
            "name" => "Мавритания",
            "position" => 95
        ],
        [
            "id" => 96,
            "name" => "Мавриций",
            "position" => 96
        ],
        [
            "id" => 97,
            "name" => "Мадагаскар",
            "position" => 97
        ],
        [
            "id" => 98,
            "name" => "Малави",
            "position" => 98
        ],
        [
            "id" => 99,
            "name" => "Малайзия",
            "position" => 99
        ],
        [
            "id" => 100,
            "name" => "Малдиви",
            "position" => 100
        ],
        [
            "id" => 101,
            "name" => "Мали",
            "position" => 101
        ],
        [
            "id" => 102,
            "name" => "Малта",
            "position" => 102
        ],
        [
            "id" => 103,
            "name" => "Мароко",
            "position" => 103
        ],
        [
            "id" => 104,
            "name" => "Маршалови острови",
            "position" => 104
        ],
        [
            "id" => 105,
            "name" => "Мексико",
            "position" => 105
        ],
        [
            "id" => 106,
            "name" => "Микронезия",
            "position" => 106
        ],
        [
            "id" => 107,
            "name" => "Мозамбик",
            "position" => 107
        ],
        [
            "id" => 108,
            "name" => "Молдова",
            "position" => 108
        ],
        [
            "id" => 109,
            "name" => "Монако",
            "position" => 109
        ],
        [
            "id" => 110,
            "name" => "Монголия",
            "position" => 110
        ],
        [
            "id" => 111,
            "name" => "Мианмар",
            "position" => 111
        ],
        [
            "id" => 112,
            "name" => "Намибия",
            "position" => 112
        ],
        [
            "id" => 113,
            "name" => "Науру",
            "position" => 113
        ],
        [
            "id" => 114,
            "name" => "Непал",
            "position" => 114
        ],
        [
            "id" => 115,
            "name" => "Нигер",
            "position" => 115
        ],
        [
            "id" => 116,
            "name" => "Нигерия",
            "position" => 116
        ],
        [
            "id" => 117,
            "name" => "Нидерландия",
            "position" => 117
        ],
        [
            "id" => 118,
            "name" => "Никарагуа",
            "position" => 118
        ],
        [
            "id" => 119,
            "name" => "Нова Зеландия",
            "position" => 119
        ],
        [
            "id" => 120,
            "name" => "Норвегия",
            "position" => 120
        ],
        [
            "id" => 121,
            "name" => "Обединени арабски емирства",
            "position" => 121
        ],
        [
            "id" => 122,
            "name" => "Оман",
            "position" => 122
        ],
        [
            "id" => 123,
            "name" => "Пакистан",
            "position" => 123
        ],
        [
            "id" => 124,
            "name" => "Палау",
            "position" => 124
        ],
        [
            "id" => 125,
            "name" => "Палестина",
            "position" => 125
        ],
        [
            "id" => 126,
            "name" => "Панама",
            "position" => 126
        ],
        [
            "id" => 127,
            "name" => "Папуа Нова Гвинея",
            "position" => 127
        ],
        [
            "id" => 128,
            "name" => "Парагвай",
            "position" => 128
        ],
        [
            "id" => 129,
            "name" => "Перу",
            "position" => 129
        ],
        [
            "id" => 130,
            "name" => "Полша",
            "position" => 130
        ],
        [
            "id" => 131,
            "name" => "Португалия",
            "position" => 131
        ],
        [
            "id" => 132,
            "name" => "Република Конго",
            "position" => 132
        ],
        [
            "id" => 133,
            "name" => "Република Корея",
            "position" => 133
        ],
        [
            "id" => 134,
            "name" => "Република Северна Македония",
            "position" => 134
        ],
        [
            "id" => 135,
            "name" => "Руанда",
            "position" => 135
        ],
        [
            "id" => 136,
            "name" => "Румъния",
            "position" => 136
        ],
        [
            "id" => 137,
            "name" => "Русия",
            "position" => 137
        ],
        [
            "id" => 138,
            "name" => "Салвадор",
            "position" => 138
        ],
        [
            "id" => 139,
            "name" => "Самоа",
            "position" => 139
        ],
        [
            "id" => 140,
            "name" => "Сан Марино",
            "position" => 140
        ],
        [
            "id" => 141,
            "name" => "Сао Томе и Принсипи",
            "position" => 141
        ],
        [
            "id" => 142,
            "name" => "Саудитска Арабия",
            "position" => 142
        ],
        [
            "id" => 143,
            "name" => "Северна Корея",
            "position" => 143
        ],
        [
            "id" => 144,
            "name" => "Сейшелски острови",
            "position" => 144
        ],
        [
            "id" => 145,
            "name" => "Сенегал",
            "position" => 145
        ],
        [
            "id" => 146,
            "name" => "Сент Винсент и Гренадини",
            "position" => 146
        ],
        [
            "id" => 147,
            "name" => "Сент Китс и Невис",
            "position" => 147
        ],
        [
            "id" => 148,
            "name" => "Сент Лусия",
            "position" => 148
        ],
        [
            "id" => 149,
            "name" => "Сиера Леоне",
            "position" => 149
        ],
        [
            "id" => 150,
            "name" => "Сингапур",
            "position" => 150
        ],
        [
            "id" => 151,
            "name" => "Сирия",
            "position" => 151
        ],
        [
            "id" => 152,
            "name" => "Словакия",
            "position" => 152
        ],
        [
            "id" => 153,
            "name" => "Словения",
            "position" => 153
        ],
        [
            "id" => 154,
            "name" => "Соломонови острови",
            "position" => 154
        ],
        [
            "id" => 155,
            "name" => "Сомалия",
            "position" => 155
        ],
        [
            "id" => 156,
            "name" => "Судан",
            "position" => 156
        ],
        [
            "id" => 157,
            "name" => "Суринам",
            "position" => 157
        ],
        [
            "id" => 158,
            "name" => "САЩ",
            "position" => 158
        ],
        [
            "id" => 159,
            "name" => "Сиера Леоне",
            "position" => 159
        ],
        [
            "id" => 160,
            "name" => "Таджикистан",
            "position" => 160
        ],
        [
            "id" => 161,
            "name" => "Тайланд",
            "position" => 161
        ],
        [
            "id" => 162,
            "name" => "Танзания",
            "position" => 162
        ],
        [
            "id" => 163,
            "name" => "Того",
            "position" => 163
        ],
        [
            "id" => 164,
            "name" => "Тонга",
            "position" => 164
        ],
        [
            "id" => 165,
            "name" => "Тринидад и Тобаго",
            "position" => 165
        ],
        [
            "id" => 166,
            "name" => "Тувалу",
            "position" => 166
        ],
        [
            "id" => 167,
            "name" => "Тунис",
            "position" => 167
        ],
        [
            "id" => 168,
            "name" => "Туркменистан",
            "position" => 168
        ],
        [
            "id" => 169,
            "name" => "Турция",
            "position" => 169
        ],
        [
            "id" => 170,
            "name" => "Уганда",
            "position" => 170
        ],
        [
            "id" => 171,
            "name" => "Узбекистан",
            "position" => 171
        ],
        [
            "id" => 172,
            "name" => "Украйна",
            "position" => 172
        ],
        [
            "id" => 173,
            "name" => "Унгария",
            "position" => 173
        ],
        [
            "id" => 174,
            "name" => "Уругвай",
            "position" => 174
        ],
        [
            "id" => 175,
            "name" => "Фиджи",
            "position" => 175
        ],
        [
            "id" => 176,
            "name" => "Филипини",
            "position" => 176
        ],
        [
            "id" => 177,
            "name" => "Финландия",
            "position" => 177
        ],
        [
            "id" => 178,
            "name" => "Франция",
            "position" => 178
        ],
        [
            "id" => 179,
            "name" => "Хаити",
            "position" => 179
        ],
        [
            "id" => 180,
            "name" => "Хондурас",
            "position" => 180
        ],
        [
            "id" => 181,
            "name" => "Хърватия",
            "position" => 181
        ],
        [
            "id" => 182,
            "name" => "Централноафриканска република",
            "position" => 182
        ],
        [
            "id" => 183,
            "name" => "Чад",
            "position" => 183
        ],
        [
            "id" => 184,
            "name" => "Черна гора",
            "position" => 184
        ],
        [
            "id" => 185,
            "name" => "Чехия",
            "position" => 185
        ],
        [
            "id" => 186,
            "name" => "Чили",
            "position" => 186
        ],
        [
            "id" => 187,
            "name" => "Швейцария",
            "position" => 187
        ],
        [
            "id" => 188,
            "name" => "Швеция",
            "position" => 188
        ],
        [
            "id" => 189,
            "name" => "Шри Ланка",
            "position" => 189
        ],
        [
            "id" => 190,
            "name" => "Еквадор",
            "position" => 190
        ],
        [
            "id" => 191,
            "name" => "Екваториална Гвинея",
            "position" => 191
        ],
        [
            "id" => 192,
            "name" => "Еритрея",
            "position" => 192
        ],
        [
            "id" => 193,
            "name" => "Южен Судан",
            "position" => 193
        ],
        [
            "id" => 194,
            "name" => "Южна Африка",
            "position" => 194
        ],
        [
            "id" => 195,
            "name" => "Ямайка",
            "position" => 195
        ],
        [
            "id" => 196,
            "name" => "Япония",
            "position" => 196
        ],
        [
            "id" => 197,
            "name" => "Друга",
            "position" => 197
        ]
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setDescription('Seeds the nationalities table with data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding nationalities table');

        try {
            // Clear existing data from the table
            $this->clearExistingData($io);

            $io->progressStart(count(self::NATIONALITY_DATA));

            // Get database connection
            $connection = $this->entityManager->getConnection();

            // Process each nationalities entry
            foreach (self::NATIONALITY_DATA as $nationalityData) {
                // Insert directly using SQL to ensure the ID is set correctly
                $sql = 'INSERT INTO nationalities (id, name, position) VALUES (:id, :name, :position)';
                $params = [
                    'id' => $nationalityData['id'],
                    'name' => $nationalityData['name'],
                    'position' => $nationalityData['position'],
                ];

                $connection->executeStatement($sql, $params);

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Nationality options have been successfully seeded!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Clears existing data from the nationalities table
     */
    private function clearExistingData(SymfonyStyle $io): void
    {
        $io->section('Clearing existing data from nationalities table');

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        // Handle database-specific operations for foreign keys
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            // For SQLite, we need to disable foreign key checks temporarily
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
        } else if ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $platform instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
            // For MySQL/MariaDB, disable foreign key checks
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        }

        // Delete all records
        $connection->executeStatement('DELETE FROM nationalities');

        // For SQLite, we would typically reset the auto-increment counter
        // For MySQL, we'll skip this step as we're explicitly setting IDs in our inserts
        if ($platform === 'sqlite') {
            // This step is commented out as we're explicitly setting IDs in our inserts
            // $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = "nationalities"');
        }

        // Re-enable foreign key checks
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        } else if ($platform instanceof \Doctrine\DBAL\Platforms\MySQLPlatform || $platform instanceof \Doctrine\DBAL\Platforms\MariaDBPlatform) {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        $io->comment('Existing data cleared');
    }
}
