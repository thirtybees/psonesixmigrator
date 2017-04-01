module.exports = function(grunt) {

    grunt.initConfig({
        compress: {
            main: {
                options: {
                    archive: 'psonesixmigrator.zip'
                },
                files: [
                    {src: ['controllers/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['classes/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['docs/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['override/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['translations/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['upgrade/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['optionaloverride/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['oldoverride/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['defaultoverride/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: ['views/**'], dest: 'psonesixmigrator/', filter: 'isFile'},
                    {src: 'config.xml', dest: 'psonesixmigrator/'},
                    {src: 'index.php', dest: 'psonesixmigrator/'},
                    {src: 'psonesixmigrator.php', dest: 'psonesixmigrator/'},
                    {src: 'logo.png', dest: 'psonesixmigrator/'},
                    {src: 'logo.gif', dest: 'psonesixmigrator/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('default', ['compress']);
};
