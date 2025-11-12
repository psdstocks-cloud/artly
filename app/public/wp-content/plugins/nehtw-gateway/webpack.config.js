const path = require('path');

module.exports = {
  entry: {
    'subscription-dashboard': './assets/js/components/SubscriptionDashboard.jsx',
  },
  output: {
    path: path.resolve(__dirname, 'assets/js'),
    filename: '[name].min.js',
    library: {
      name: 'SubscriptionDashboard',
      type: 'window',
    },
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react'],
          },
        },
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader'],
      },
    ],
  },
  externals: {
    'react': 'React',
    'react-dom': 'ReactDOM',
  },
  resolve: {
    extensions: ['.js', '.jsx'],
  },
};

